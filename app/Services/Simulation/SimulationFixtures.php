<?php

namespace App\Services\Simulation;

use App\Ai\Agents\Simulation\SimulationFaqFixtureWriter;
use App\Ai\Agents\Simulation\SimulationOffTopicFixtureWriter;
use App\Models\FaqEntry;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Synthetic inbound questions and gold answers, generated once per FAQ on
 * first use and cached in a JSON file so every later run (and the
 * loop-on/loop-off pair) reuses exactly the same data.
 *
 * Each entry records which provider and model wrote it, so fixtures from
 * a cheap local debugging run can be told apart and regenerated.
 */
class SimulationFixtures
{
    /**
     * @var array{faqs: array<string, array{paraphrases: list<string>, gold_answer: string, written_by: string}>, off_topic: ?array{out_of_scope: list<string>, noise: list<string>, written_by: string}}
     */
    private array $data;

    public function __construct(private string $path)
    {
        $this->data = File::exists($path)
            ? json_decode(File::get($path), associative: true, flags: JSON_THROW_ON_ERROR)
            : ['faqs' => [], 'off_topic' => null];
    }

    public function questionFor(string $kind, ?string $faqId, int $variant): string
    {
        $pool = match ($kind) {
            SimulationSchedule::KindFaq => $this->faq((string) $faqId)['paraphrases'],
            SimulationSchedule::KindOutOfScope => $this->offTopic()['out_of_scope'],
            SimulationSchedule::KindNoise => $this->offTopic()['noise'],
        };

        return $pool[$variant % count($pool)];
    }

    public function goldAnswerFor(string $faqId): string
    {
        return $this->faq($faqId)['gold_answer'];
    }

    /**
     * @return array{paraphrases: list<string>, gold_answer: string, written_by: string}
     */
    private function faq(string $faqId): array
    {
        if (! isset($this->data['faqs'][$faqId])) {
            $faq = FaqEntry::query()->findOrFail($faqId);

            $response = (new SimulationFaqFixtureWriter)->prompt(sprintf(
                "FAQ question:\n%s\n\nFAQ answer:\n%s",
                $faq->question,
                $faq->answer,
            ));

            $paraphrases = $this->strings($response['paraphrases'] ?? null);
            $goldAnswer = trim((string) ($response['gold_answer'] ?? ''));

            if ($paraphrases === [] || $goldAnswer === '') {
                throw new RuntimeException("Fixture writer returned no usable data for FAQ [{$faqId}].");
            }

            $this->data['faqs'][$faqId] = [
                'paraphrases' => $paraphrases,
                'gold_answer' => $goldAnswer,
                'written_by' => $this->writtenBy(),
            ];

            $this->save();
        }

        return $this->data['faqs'][$faqId];
    }

    /**
     * @return array{out_of_scope: list<string>, noise: list<string>, written_by: string}
     */
    private function offTopic(): array
    {
        if ($this->data['off_topic'] === null) {
            $response = (new SimulationOffTopicFixtureWriter)->prompt('Write the messages now.');

            $outOfScope = $this->strings($response['out_of_scope'] ?? null);
            $noise = $this->strings($response['noise'] ?? null);

            if ($outOfScope === [] || $noise === []) {
                throw new RuntimeException('Off-topic fixture writer returned no usable data.');
            }

            $this->data['off_topic'] = [
                'out_of_scope' => $outOfScope,
                'noise' => $noise,
                'written_by' => $this->writtenBy(),
            ];

            $this->save();
        }

        return $this->data['off_topic'];
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values),
            fn (string $value): bool => $value !== '',
        ));
    }

    private function writtenBy(): string
    {
        $provider = (string) config('ai.default');

        return $provider.':'.(config("ai.providers.{$provider}.models.text.default") ?? 'provider-default');
    }

    private function save(): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
