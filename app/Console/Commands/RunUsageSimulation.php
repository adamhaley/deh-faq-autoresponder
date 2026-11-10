<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\EmailQuestion;
use App\Models\FaqEntry;
use App\Models\GmailMailbox;
use App\Models\GmailMessage;
use App\Models\User;
use App\Services\EmailQuestions\AnswerSemanticSimilarityScorer;
use App\Services\EmailQuestions\EmailQuestionClassificationService;
use App\Services\EmailQuestions\EmailQuestionExtractionService;
use App\Services\Simulation\SimulatedReviewer;
use App\Services\Simulation\SimulationFixtures;
use App\Services\Simulation\SimulationGuard;
use App\Services\Simulation\SimulationSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\FaqEntrySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LOCAL ONLY. Runs the real pipeline over simulated weeks against a
 * dedicated deh_simulation* database, with no Gmail and a simulated
 * reviewer. Resumable: rerunning with the same options skips work that
 * is already done.
 */
#[Signature('simulation:run
    {--database=deh_simulation : Simulation database (must start with deh_simulation)}
    {--loop=on : on, or off for the control run}
    {--weeks=12} {--per-week=25} {--seed=42}
    {--approve-threshold=90 : Meaning closeness (0-100) at which the reviewer approves a draft unchanged}
    {--provider= : Text provider for the pipeline and fixtures, e.g. openai or ollama}
    {--model= : Text model, e.g. gpt-5-mini or qwen3.5:9b}
    {--fixtures= : Fixture file (default storage/app/private/simulation/fixtures.json)}
    {--fresh : Recreate the simulation database first}')]
#[Description('LOCAL ONLY: simulate weeks of consistent use against a simulation database')]
class RunUsageSimulation extends Command
{
    public const StartMonday = '2026-01-05';

    public const ReviewerEmail = 'simulierte-pruefung@example.test';

    /** Production retries classification on the next scheduler tick. */
    private const ClassificationAttempts = 3;

    private const RetryDelayMilliseconds = 5000;

    public function handle(SimulationGuard $guard, EmailQuestionExtractionService $extraction, EmailQuestionClassificationService $classification, AnswerSemanticSimilarityScorer $scorer): int
    {
        $guard->enter((string) $this->option('database'));
        $guard->useTextModel($this->option('provider') ?: null, $this->option('model') ?: null);

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--class' => FaqEntrySeeder::class, '--force' => true]);
        }

        $faqIds = FaqEntry::query()->orderBy('id')->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

        if ($faqIds === []) {
            $this->error('No FAQ entries in the simulation database. Rerun with --fresh.');

            return self::FAILURE;
        }

        $mailbox = $this->simulationMailbox();
        $reviewer = $this->simulationUsers();
        $loopEnabled = $this->option('loop') !== 'off';
        $seed = (int) $this->option('seed');
        $fixtures = new SimulationFixtures($this->option('fixtures') ?: storage_path('app/private/simulation/fixtures.json'));
        $simulatedReviewer = new SimulatedReviewer($reviewer, $fixtures, $scorer, (int) $this->option('approve-threshold'), $loopEnabled);

        $events = collect((new SimulationSchedule)->build(
            $seed,
            (int) $this->option('weeks'),
            (int) $this->option('per-week'),
            $faqIds,
            CarbonImmutable::parse(self::StartMonday),
        ));

        $this->info(sprintf(
            'Simulating %d messages on [%s], loop %s, model %s.',
            $events->count(),
            $this->option('database'),
            $loopEnabled ? 'ON' : 'OFF',
            $this->textModel(),
        ));

        try {
            $events->groupBy('day')->each(function (Collection $dayEvents, string $day) use ($mailbox, $seed, $fixtures, $extraction, $classification, $simulatedReviewer): void {
                $startedAt = microtime(true);

                $dayEvents->each(function (array $event) use ($mailbox, $seed, $fixtures, $extraction, $classification): void {
                    $message = $this->deliver($event, $mailbox, $seed, $fixtures);

                    // Per message, at its own arrival time, so a resumed run
                    // never processes a later day's message early.
                    Carbon::setTestNow($event['arrives_at']->addMinute());

                    if ($message->questions_extracted_at === null) {
                        $extraction->extractMessage($message);
                        $message->update(['questions_extracted_at' => now()]);
                    }

                    $message->questions()->whereNull('classified_at')->get()
                        ->each(fn (EmailQuestion $question) => retry(
                            self::ClassificationAttempts,
                            fn () => $classification->classify($question),
                            self::RetryDelayMilliseconds,
                        ));
                });

                $failuresBefore = count($simulatedReviewer->failures);
                $simulatedReviewer->reviewAt(SimulationSchedule::reviewTimeFor($day));

                $this->line(sprintf(
                    'Week %d, %s: %d message(s), %d failure(s), %ds.',
                    $dayEvents->first()['week'],
                    $day,
                    $dayEvents->count(),
                    count($simulatedReviewer->failures) - $failuresBefore,
                    (int) round(microtime(true) - $startedAt),
                ));
            });
        } finally {
            Carbon::setTestNow();
        }

        foreach ($simulatedReviewer->failures as $failure) {
            $this->warn($failure);
        }

        $this->info('Done. Run simulation:report for results.');

        return $simulatedReviewer->failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{index: int, week: int, day: string, kind: string, faq_id: ?string, variant: int, arrives_at: CarbonImmutable}  $event
     */
    private function deliver(array $event, GmailMailbox $mailbox, int $seed, SimulationFixtures $fixtures): GmailMessage
    {
        $gmailMessageId = "sim-{$seed}-{$event['index']}";
        $existing = GmailMessage::query()->where('gmail_message_id', $gmailMessageId)->first();

        if ($existing !== null) {
            return $existing;
        }

        Carbon::setTestNow($event['arrives_at']);

        $question = $fixtures->questionFor($event['kind'], $event['faq_id'], $event['variant']);
        $participant = 'Teilnehmer '.($event['index'] + 1);

        return GmailMessage::query()->create([
            'gmail_mailbox_id' => $mailbox->id,
            'gmail_message_id' => $gmailMessageId,
            // No thread id: the composer (and with it every Gmail call) only
            // runs for messages that belong to a Gmail thread.
            'thread_id' => null,
            'subject' => 'Webinar-Chat (simuliert) '.$event['day'],
            'from_email' => 'teilnehmer'.($event['index'] + 1).'@example.test',
            'from_name' => $participant,
            'participant_name' => $participant,
            'snippet' => $question,
            'text_body' => sprintf(
                "Vorname: Teilnehmer\nNachname: %d\n\nChat:\n1: %s (%s): %s",
                $event['index'] + 1,
                $participant,
                $event['arrives_at']->format('d/m/Y H:i'),
                $question,
            ),
            'internal_date' => $event['arrives_at'],
            'imported_at' => $event['arrives_at'],
            'payload' => [
                'simulation' => [
                    'index' => $event['index'],
                    'week' => $event['week'],
                    'kind' => $event['kind'],
                    'faq_id' => $event['faq_id'],
                    'seed' => $seed,
                ],
            ],
        ]);
    }

    private function simulationMailbox(): GmailMailbox
    {
        return GmailMailbox::query()->firstOrCreate(
            ['email' => 'simulation@example.test'],
            [
                'access_token' => 'simulation-no-token',
                'is_active' => false,
                'sync_status' => 'simulation',
            ],
        );
    }

    private function simulationUsers(): User
    {
        User::query()->firstOrCreate(
            ['email' => 'simulation-admin@example.test'],
            ['name' => 'Simulation Admin', 'password' => 'password', 'role' => UserRole::Admin, 'is_active' => true],
        );

        return User::query()->firstOrCreate(
            ['email' => self::ReviewerEmail],
            ['name' => 'Simulierte Prüferin', 'password' => str()->random(32), 'role' => UserRole::Reviewer, 'is_active' => true],
        );
    }

    private function textModel(): string
    {
        $provider = (string) config('ai.default');

        return $provider.':'.(config("ai.providers.{$provider}.models.text.default") ?? 'provider default');
    }
}
