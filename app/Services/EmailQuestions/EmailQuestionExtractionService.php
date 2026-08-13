<?php

namespace App\Services\EmailQuestions;

use App\Models\EmailQuestion;
use App\Models\GmailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailQuestionExtractionService
{
    public const ParserVersion = 'n8n-chat-v1';

    public function extractPendingMessages(int $limit = 100): int
    {
        $extractedQuestions = 0;

        GmailMessage::query()
            ->doesntHave('questions')
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (GmailMessage $message) use (&$extractedQuestions): void {
                $extractedQuestions += $this->extractMessage($message)->count();
            });

        return $extractedQuestions;
    }

    /**
     * @return Collection<int, EmailQuestion>
     */
    public function extractMessage(GmailMessage $message): Collection
    {
        $candidates = $this->questionCandidates($message);

        return collect($candidates)
            ->map(function (array $candidate, int $index) use ($message): EmailQuestion {
                $questionText = $candidate['question_text'];

                return EmailQuestion::query()->updateOrCreate(
                    [
                        'gmail_message_id' => $message->id,
                        'question_hash' => $this->questionHash($questionText),
                    ],
                    [
                        'question_order' => $index + 1,
                        'question_text' => $questionText,
                        'parser_version' => self::ParserVersion,
                        'extraction_metadata' => [
                            'source' => $candidate['source'],
                            'parser_version' => self::ParserVersion,
                        ],
                    ],
                );
            });
    }

    /**
     * @return list<array{question_text: string, source: string}>
     */
    private function questionCandidates(GmailMessage $message): array
    {
        $text = $this->messageText($message);

        if ($text === '') {
            return [];
        }

        $chatCandidates = $this->chatQuestionCandidates($text);

        if ($chatCandidates !== []) {
            return $chatCandidates;
        }

        $fallback = $this->fallbackQuestionCandidate($text);

        if ($fallback === null) {
            return [];
        }

        return [
            [
                'question_text' => $fallback,
                'source' => 'body_fallback',
            ],
        ];
    }

    /**
     * @return list<array{question_text: string, source: string}>
     */
    private function chatQuestionCandidates(string $text): array
    {
        $parts = preg_split('/Chat:/i', $text, 2);

        if (! is_array($parts) || count($parts) < 2) {
            return [];
        }

        $questions = [];
        $chatSection = $parts[1];

        preg_match_all('/\d+:\s.*?\):\s([\s\S]*?)(?=\n\s*\d+:\s|$)/u', $chatSection, $matches);

        foreach ($matches[1] ?? [] as $match) {
            $questionText = $this->cleanQuestionText($match);

            if ($questionText === null) {
                continue;
            }

            $questions[] = [
                'question_text' => $questionText,
                'source' => 'chat_section',
            ];
        }

        return $questions;
    }

    private function cleanQuestionText(string $value): ?string
    {
        $cleaned = preg_replace('/<br\s*\/?>/i', ' ', $value) ?? $value;
        $cleaned = strip_tags($cleaned);
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = preg_split('/Viele Grüße,|Vielen Dank|Thank you in advance|Best regards|Kind regards|Webinaris\s*\(|Hinweis:|Note:/iu', $cleaned, 2)[0] ?? $cleaned;
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return null;
        }

        if (Str::length($cleaned) < 8) {
            return null;
        }

        return $cleaned;
    }

    private function fallbackQuestionCandidate(string $text): ?string
    {
        $withoutUrls = preg_replace('/https?:\/\/\S+/iu', ' ', $text) ?? $text;
        $questionText = $this->cleanQuestionText($withoutUrls);

        if ($questionText === null || ! str_contains($questionText, '?')) {
            return null;
        }

        if (Str::length($questionText) > 500) {
            return null;
        }

        return $questionText;
    }

    private function messageText(GmailMessage $message): string
    {
        if (is_string($message->text_body) && trim($message->text_body) !== '') {
            return $message->text_body;
        }

        if (is_string($message->html_body) && trim($message->html_body) !== '') {
            return $message->html_body;
        }

        if (is_string($message->snippet) && trim($message->snippet) !== '') {
            return $message->snippet;
        }

        return '';
    }

    private function questionHash(string $questionText): string
    {
        return hash('sha256', Str::lower($questionText));
    }
}
