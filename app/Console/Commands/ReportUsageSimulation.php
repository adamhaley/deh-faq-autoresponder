<?php

namespace App\Console\Commands;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Services\Simulation\SimulationGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Per-week results of a simulated usage run. "Closeness" is the meaning
 * similarity (0-100) between the AI draft and the gold answer the
 * simulated reviewer wants, measured before the reviewer acts.
 */
#[Signature('simulation:report {--database=deh_simulation}')]
#[Description('LOCAL ONLY: per-week results of a simulated usage run')]
class ReportUsageSimulation extends Command
{
    public function handle(SimulationGuard $guard): int
    {
        $database = (string) $this->option('database');
        $guard->enter($database);

        $drafts = EmailQuestionAnswerDraft::query()
            ->where('status', EmailQuestionAnswerDraft::StatusApproved)
            ->whereNotNull('generation_metadata->simulation')
            ->with('emailQuestion.message')
            ->get();

        $questionsByWeek = EmailQuestion::query()
            ->with('message')
            ->get()
            ->groupBy(fn (EmailQuestion $question): int => (int) ($question->message->payload['simulation']['week'] ?? 0));

        $rows = $drafts
            ->groupBy(fn (EmailQuestionAnswerDraft $draft): int => (int) $draft->emailQuestion->message->payload['simulation']['week'])
            ->sortKeys()
            ->map(function (Collection $weekDrafts, int $week) use ($questionsByWeek): array {
                $simulation = $weekDrafts->map(fn (EmailQuestionAnswerDraft $draft): array => $draft->generation_metadata['simulation']);
                $repeats = $simulation->where('repeat', true);
                $firsts = $simulation->where('repeat', false);
                $weekQuestions = $questionsByWeek->get($week, collect());

                return [
                    'week' => $week,
                    'questions' => $weekQuestions->count(),
                    'ai_agreed' => $this->percent($weekQuestions->filter(fn (EmailQuestion $question): bool => $this->agrees($question))->count(), $weekQuestions->count()),
                    'drafts' => $weekDrafts->count(),
                    'closeness' => $this->average($simulation->pluck('gold_closeness')),
                    'unchanged' => $this->percent($simulation->where('approved_unchanged', true)->count(), $simulation->count()),
                    'repeat_drafts' => $repeats->count(),
                    'repeat_closeness' => $this->average($repeats->pluck('gold_closeness')),
                    'first_closeness' => $this->average($firsts->pluck('gold_closeness')),
                    'top_match_correct' => $this->percent($simulation->where('top_match_correct', true)->count(), $simulation->count()),
                ];
            })
            ->values();

        $this->info("Simulated data on [{$database}]. Not real client results.");
        $this->table(array_keys($rows->first() ?? ['week' => null]), $rows->all());

        $path = storage_path("app/private/simulation/{$database}-report.csv");
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($rows->first() ?? ['week' => null]));
        $rows->each(fn (array $row) => fputcsv($handle, $row));
        fclose($handle);

        $this->line("CSV: {$path}");

        return self::SUCCESS;
    }

    /**
     * The decision that matters: does this need an answer, yes or no.
     */
    private function agrees(EmailQuestion $question): bool
    {
        if ($question->review_status === EmailQuestion::ReviewStatusPendingReview) {
            return false;
        }

        return ($question->classification === EmailQuestion::ClassificationValidFaqQuestion)
            === ($question->review_status === EmailQuestion::ReviewStatusValid);
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function average(Collection $values): ?float
    {
        $numbers = $values->filter(fn (mixed $value): bool => is_numeric($value));

        return $numbers->isEmpty() ? null : round($numbers->avg(), 1);
    }

    private function percent(int $part, int $total): ?float
    {
        return $total === 0 ? null : round(100 * $part / $total, 1);
    }
}
