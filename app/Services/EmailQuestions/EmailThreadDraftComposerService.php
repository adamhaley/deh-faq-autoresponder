<?php

namespace App\Services\EmailQuestions;

use App\Ai\Agents\EmailGreetingGenerator;
use App\Enums\AnswerDraftStatus;
use App\Enums\EmailThreadDraftStatus;
use App\Models\EmailQuestion;
use App\Models\EmailTemplate;
use App\Models\EmailThreadDraft;
use App\Models\GmailMessage;
use App\Services\Gmail\GmailClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Assembles every approved answer for a Gmail thread into one composed
 * reply and creates (or updates) the corresponding Gmail draft.
 *
 * Runs whenever a question's answer is approved or edited-while-approved --
 * the answer draft is the single source of truth, so this is safe to
 * re-run repeatedly and will keep the Gmail draft in sync.
 */
class EmailThreadDraftComposerService
{
    public function __construct(
        private readonly GmailClient $gmail,
    ) {}

    public function composeForThread(string $threadId): ?EmailThreadDraft
    {
        $questions = EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusValid)
            ->whereHas('message', fn ($query) => $query->where('thread_id', $threadId))
            ->with(['answerDraft', 'message.mailbox'])
            ->get()
            ->sortBy(fn (EmailQuestion $question) => $question->question_order)
            ->sortBy(fn (EmailQuestion $question) => $question->message->internal_date)
            ->values();

        if ($questions->isEmpty()) {
            return null;
        }

        $notReady = $questions->contains(
            fn (EmailQuestion $question) => $question->answerDraft?->status !== AnswerDraftStatus::Approved,
        );

        if ($notReady) {
            return null;
        }

        $template = EmailTemplate::query()->first();

        if ($template === null) {
            Log::warning('No email template configured; cannot compose thread draft.', [
                'thread_id' => $threadId,
            ]);

            return null;
        }

        $latestMessage = $questions->last()->message;
        $participantMessage = $questions->first(
            fn (EmailQuestion $question) => $question->message->participant_name !== null,
        )?->message ?? $latestMessage;

        $mailbox = $latestMessage->mailbox;
        $to = $participantMessage->reply_to_email ?? $participantMessage->from_email;

        if ($to === null) {
            Log::warning('No recipient email available for thread draft.', [
                'thread_id' => $threadId,
            ]);

            return null;
        }

        $draft = EmailThreadDraft::query()->firstOrNew(['thread_id' => $threadId]);
        $wasExisting = $draft->exists;

        $draft->gmail_mailbox_id = $mailbox->id;
        $draft->subject = $template->subject;
        $draft->body = $template->renderBody(
            $this->generateGreeting($participantMessage->participant_name),
            $this->joinQuestionsAndAnswers($questions),
        );

        $raw = $this->buildRawMessage($to, $draft->subject, $draft->body, $this->messageIdHeader($latestMessage));

        try {
            $response = $draft->gmail_draft_id !== null
                ? $this->gmail->updateDraft($mailbox, $draft->gmail_draft_id, $raw, $threadId)
                : $this->gmail->createDraft($mailbox, $raw, $threadId);

            $draft->gmail_draft_id = $response['id'] ?? $draft->gmail_draft_id;
            $draft->status = $wasExisting ? EmailThreadDraftStatus::Updated : EmailThreadDraftStatus::Created;
            $draft->last_error = null;
            $draft->composed_at = now();
        } catch (Throwable $exception) {
            $draft->status = EmailThreadDraftStatus::Failed;
            $draft->last_error = $exception->getMessage();

            report($exception);
        }

        $draft->save();

        return $draft;
    }

    private function generateGreeting(?string $participantName): string
    {
        if ($participantName === null || trim($participantName) === '') {
            return 'Sehr geehrte Damen und Herren';
        }

        $response = (new EmailGreetingGenerator)->prompt("The incoming name is: {$participantName}");

        $greeting = is_string($response['greeting'] ?? null) ? trim($response['greeting']) : '';

        return $greeting !== '' ? $greeting : 'Sehr geehrte Damen und Herren';
    }

    /**
     * @param  Collection<int, EmailQuestion>  $questions
     */
    private function joinQuestionsAndAnswers(Collection $questions): string
    {
        return $questions
            ->map(fn (EmailQuestion $question): string => sprintf(
                "<p><strong>Ihre Frage:</strong> %s</p>\n<p>%s</p>",
                e($question->question_text),
                nl2br(e($question->answerDraft->final_answer)),
            ))
            ->implode("\n\n");
    }

    private function messageIdHeader(GmailMessage $message): ?string
    {
        $payload = is_array($message->payload) ? $message->payload : [];

        foreach ($payload['headers'] ?? [] as $header) {
            if (is_array($header) && Str::lower($header['name'] ?? '') === 'message-id' && is_string($header['value'] ?? null)) {
                return $header['value'];
            }
        }

        return null;
    }

    private function buildRawMessage(string $to, string $subject, string $bodyContent, ?string $inReplyTo): string
    {
        $headers = [
            'MIME-Version: 1.0',
            "To: {$to}",
            'Subject: '.$this->encodeHeader($subject),
            'Content-Type: text/html; charset="UTF-8"',
            'Content-Transfer-Encoding: base64',
        ];

        if ($inReplyTo !== null) {
            $headers[] = "In-Reply-To: {$inReplyTo}";
            $headers[] = "References: {$inReplyTo}";
        }

        $htmlBody = $this->wrapHtmlDocument($bodyContent);

        $message = implode("\r\n", $headers)."\r\n\r\n".chunk_split(base64_encode($htmlBody), 76, "\r\n");

        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }

    /**
     * EmailTemplate::body is stored as an editable content fragment (what
     * the rich editor can safely round-trip). This wraps it in the minimal
     * boilerplate an HTML email part needs -- purely mechanical, so it
     * lives in code rather than in the editable template content.
     */
    private function wrapHtmlDocument(string $content): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head><meta http-equiv="content-type" content="text/html; charset=UTF-8"></head>
            <body>{$content}</body>
            </html>
            HTML;
    }
}
