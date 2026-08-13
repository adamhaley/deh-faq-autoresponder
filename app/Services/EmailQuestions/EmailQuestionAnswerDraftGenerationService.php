<?php

namespace App\Services\EmailQuestions;

use App\Ai\Agents\EmailQuestionAnswerGenerator;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailQuestionFaqMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailQuestionAnswerDraftGenerationService
{
    public function generateForReadyQuestions(int $limit = 50): int
    {
        $generatedDrafts = 0;

        EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusValid)
            ->whereHas('faqMatches')
            ->whereDoesntHave('answerDraft')
            ->with(['faqMatches.faqEntry.approvedResponse', 'message:id,subject,from_email,snippet,text_body'])
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (EmailQuestion $question) use (&$generatedDrafts): void {
                $this->generate($question);
                $generatedDrafts++;
            });

        return $generatedDrafts;
    }

    public function generate(EmailQuestion $question): EmailQuestionAnswerDraft
    {
        $startedAt = hrtime(true);
        $loadStartedAt = hrtime(true);
        $question->loadMissing(['faqMatches.faqEntry.approvedResponse', 'message:id,subject,from_email,snippet,text_body']);
        $loadElapsedMs = $this->elapsedMilliseconds($loadStartedAt);

        $matches = $question->faqMatches;

        if ($matches->isEmpty()) {
            throw new \RuntimeException('FAQ matches must be retrieved before generating an answer draft.');
        }

        $generationStartedAt = hrtime(true);
        $response = (new EmailQuestionAnswerGenerator)->prompt($this->prompt($question, $matches));
        $generationElapsedMs = $this->elapsedMilliseconds($generationStartedAt);
        $answer = is_string($response['answer'] ?? null) ? trim($response['answer']) : '';
        $reason = is_string($response['reason'] ?? null) ? trim($response['reason']) : null;

        if ($answer === '') {
            throw new \RuntimeException('Answer generator did not return a usable answer.');
        }

        $persistenceStartedAt = hrtime(true);
        $draft = EmailQuestionAnswerDraft::query()->updateOrCreate(
            ['email_question_id' => $question->id],
            [
                'generated_answer' => $answer,
                'final_answer' => $answer,
                'status' => EmailQuestionAnswerDraft::StatusDraft,
                'generation_reason' => $reason,
                'generation_metadata' => [
                    'agent' => EmailQuestionAnswerGenerator::class,
                    'faq_match_ids' => $matches->pluck('id')->values()->all(),
                    'faq_entry_ids' => $matches->pluck('faq_entry_id')->values()->all(),
                    'retrieval_similarity' => $matches
                        ->mapWithKeys(fn (EmailQuestionFaqMatch $match): array => [
                            (string) $match->faq_entry_id => $match->similarity,
                        ])
                        ->all(),
                ],
                'generated_at' => now(),
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ],
        )->load(['emailQuestion', 'reviewer']);
        $persistenceElapsedMs = $this->elapsedMilliseconds($persistenceStartedAt);

        Log::info('Email question answer draft generation completed.', [
            'email_question_id' => $question->id,
            'answer_draft_id' => $draft->id,
            'faq_match_count' => $matches->count(),
            'load_ms' => $loadElapsedMs,
            'generation_ms' => $generationElapsedMs,
            'persistence_ms' => $persistenceElapsedMs,
            'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        return $draft;
    }

    /**
     * @param  Collection<int, EmailQuestionFaqMatch>  $matches
     */
    private function prompt(EmailQuestion $question, Collection $matches): string
    {
        return sprintf(
            <<<'PROMPT'
Generate a draft answer segment for this reviewed-valid customer FAQ question.

User question:
%s

Email context:
Subject: %s
From: %s
Snippet: %s

Retrieved FAQ matches:
%s
PROMPT,
            $question->normalized_question ?: $question->question_text,
            $question->message?->subject ?? '',
            $question->message?->from_email ?? '',
            Str::limit($question->message?->snippet ?? '', 500),
            $this->formatMatches($matches),
        );
    }

    /**
     * @param  Collection<int, EmailQuestionFaqMatch>  $matches
     */
    private function formatMatches(Collection $matches): string
    {
        return $matches
            ->map(function (EmailQuestionFaqMatch $match): string {
                $faqEntry = $match->faqEntry;
                $approvedResponse = $faqEntry?->approvedResponse?->approved_response;

                return sprintf(
                    <<<'MATCH'
Match %d
Similarity: %.1f%%
FAQ question: %s
FAQ answer: %s
Human-approved response: %s
MATCH,
                    $match->rank,
                    $match->similarity * 100,
                    $faqEntry?->question ?? '',
                    $faqEntry?->answer ?? '',
                    $approvedResponse ?: 'No human-approved response available.',
                );
            })
            ->implode("\n\n");
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
