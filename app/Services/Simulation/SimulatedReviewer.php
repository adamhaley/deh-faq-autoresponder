<?php

namespace App\Services\Simulation;

use App\Jobs\RetrieveEmailQuestionFaqMatches;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\FaqApprovedResponse;
use App\Models\User;
use App\Services\EmailQuestions\AnswerSemanticSimilarityScorer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A reviewer who reviews every day and always the same way: the opposite
 * of DEH's real, bursty pattern, and the point of the simulation.
 *
 * Question verdicts come from the fixture's ground truth. Drafts are
 * approved unchanged when they are close enough in meaning to the gold
 * answer, otherwise replaced with the gold answer. Approvals go through
 * the real model methods, so they feed the real self-improvement loop.
 */
class SimulatedReviewer
{
    /** @var list<string> */
    public array $failures = [];

    private CarbonImmutable $clock;

    public function __construct(
        private User $reviewer,
        private SimulationFixtures $fixtures,
        private AnswerSemanticSimilarityScorer $scorer,
        private int $approveThreshold,
        private bool $loopEnabled,
    ) {}

    public function reviewAt(CarbonImmutable $reviewTime): void
    {
        $this->clock = $reviewTime;

        // Only what had arrived by this review: on a resumed run, later
        // days' questions must wait for their own review.
        $arrivedBeforeReview = fn ($messages) => $messages->where('internal_date', '<=', $reviewTime);

        EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusPendingReview)
            ->whereNotNull('classified_at')
            ->whereHas('message', $arrivedBeforeReview)
            ->with('message')
            ->oldest('id')
            ->get()
            ->each(fn (EmailQuestion $question) => $this->reviewQuestion($question));

        EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusValid)
            ->whereHas('message', $arrivedBeforeReview)
            ->whereDoesntHave('answerDraft', fn ($drafts) => $drafts->whereIn('status', [
                EmailQuestionAnswerDraft::StatusApproved,
                EmailQuestionAnswerDraft::StatusRejected,
            ]))
            ->with(['message', 'answerDraft'])
            ->oldest('id')
            ->get()
            ->each(fn (EmailQuestion $question) => $this->reviewDraft($question));
    }

    private function reviewQuestion(EmailQuestion $question): void
    {
        $this->tick();

        $verdict = match ($this->groundTruth($question)['kind']) {
            SimulationSchedule::KindFaq => EmailQuestion::ReviewStatusValid,
            SimulationSchedule::KindOutOfScope => EmailQuestion::ReviewStatusUnanswerable,
            SimulationSchedule::KindNoise => EmailQuestion::ReviewStatusNoise,
        };

        $this->attempt("review question {$question->id}", fn () => $question->markReviewed($verdict, $this->reviewer->id));
    }

    private function reviewDraft(EmailQuestion $question): void
    {
        $this->tick();

        if ($question->answerDraft?->status !== EmailQuestionAnswerDraft::StatusDraft) {
            // Retrieval or generation failed earlier (or the run stopped
            // midway): rerun the real retrieval job, which chains into
            // generation.
            $this->attempt("retry pipeline for question {$question->id}", fn () => RetrieveEmailQuestionFaqMatches::dispatchSync($question->id));
            $question->load('answerDraft');
        }

        $draft = $question->answerDraft;

        if ($draft?->status !== EmailQuestionAnswerDraft::StatusDraft) {
            $this->failures[] = "question {$question->id}: no draft to review";

            return;
        }

        $this->attempt("review draft {$draft->id}", function () use ($question, $draft): void {
            $faqId = (string) $this->groundTruth($question)['faq_id'];
            $goldAnswer = $this->fixtures->goldAnswerFor($faqId);
            $closeness = $this->scorer->score((string) $draft->generated_answer, $goldAnswer);
            $approvedUnchanged = $closeness !== null && $closeness >= $this->approveThreshold;
            $topMatch = $question->faqMatches()->first();

            $draft->updateQuietly([
                'generation_metadata' => [
                    ...($draft->generation_metadata ?? []),
                    'simulation' => [
                        'ground_truth_faq_id' => $faqId,
                        'gold_closeness' => $closeness,
                        'approved_unchanged' => $approvedUnchanged,
                        'repeat' => $this->faqAlreadyApproved($faqId),
                        'top_match_correct' => $topMatch?->faq_entry_id === $faqId,
                        'approved_response_in_prompt' => $question->faqMatches()->whereHas('faqEntry.approvedResponse')->exists(),
                        'loop_enabled' => $this->loopEnabled,
                    ],
                ],
            ]);

            $draft->markReviewed(
                EmailQuestionAnswerDraft::StatusApproved,
                $this->reviewer->id,
                $approvedUnchanged ? $draft->generated_answer : $goldAnswer,
            );

            if (! $this->loopEnabled) {
                FaqApprovedResponse::query()->delete();
            }
        });
    }

    private function faqAlreadyApproved(string $faqId): bool
    {
        return EmailQuestionAnswerDraft::query()
            ->where('status', EmailQuestionAnswerDraft::StatusApproved)
            ->where('generation_metadata->simulation->ground_truth_faq_id', $faqId)
            ->exists();
    }

    /**
     * @return array{kind: string, faq_id: ?string}
     */
    private function groundTruth(EmailQuestion $question): array
    {
        return $question->message->payload['simulation'];
    }

    private function tick(): void
    {
        $this->clock = $this->clock->addMinute();
        Carbon::setTestNow($this->clock);
    }

    private function attempt(string $label, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            $this->failures[] = "{$label}: {$exception->getMessage()}";
        }
    }
}
