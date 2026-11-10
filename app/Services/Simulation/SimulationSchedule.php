<?php

namespace App\Services\Simulation;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deterministic arrival schedule for the simulated usage run. The same
 * seed and parameters always produce the same events, so a run can be
 * resumed and the loop-on and loop-off runs see identical input.
 *
 * Shaped like DEH's real traffic: questions arrive in bursts after two
 * webinar evenings a week, a few FAQ topics come up far more often than
 * the rest, and some messages are out of scope or noise.
 */
class SimulationSchedule
{
    public const KindFaq = 'faq';

    public const KindOutOfScope = 'out_of_scope';

    public const KindNoise = 'noise';

    public const OutOfScopeShare = 0.1;

    public const NoiseShare = 0.1;

    public const WebinarStart = '20:30';

    public const ReviewStart = '10:00';

    /** Tuesday and Thursday, as day offsets from the week's Monday. */
    private const WebinarDayOffsets = [1, 3];

    /**
     * @param  list<string>  $faqIds
     * @return list<array{index: int, week: int, day: string, kind: string, faq_id: ?string, variant: int, arrives_at: CarbonImmutable}>
     */
    public function build(int $seed, int $weeks, int $perWeek, array $faqIds, CarbonImmutable $startMonday): array
    {
        $randomizer = new Randomizer(new Mt19937($seed));
        $popularity = $this->popularityWeights($randomizer->shuffleArray($faqIds));

        $events = [];
        $index = 0;

        for ($week = 1; $week <= $weeks; $week++) {
            $monday = $startMonday->addWeeks($week - 1);
            $perWebinar = $this->splitEvenly($perWeek, count(self::WebinarDayOffsets));

            foreach (self::WebinarDayOffsets as $slot => $dayOffset) {
                $webinarEnd = $monday->addDays($dayOffset)->setTimeFromTimeString(self::WebinarStart);

                for ($i = 0; $i < $perWebinar[$slot]; $i++) {
                    $kind = $this->kind($randomizer->nextFloat());

                    $events[] = [
                        'index' => $index++,
                        'week' => $week,
                        'day' => $webinarEnd->toDateString(),
                        'kind' => $kind,
                        'faq_id' => $kind === self::KindFaq ? $this->weightedPick($randomizer, $popularity) : null,
                        'variant' => $randomizer->getInt(0, 1000),
                        'arrives_at' => $webinarEnd->addMinutes(2 * $i),
                    ];
                }
            }
        }

        return $events;
    }

    public static function reviewTimeFor(string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day)->addDay()->setTimeFromTimeString(self::ReviewStart);
    }

    private function kind(float $roll): string
    {
        return match (true) {
            $roll < self::OutOfScopeShare => self::KindOutOfScope,
            $roll < self::OutOfScopeShare + self::NoiseShare => self::KindNoise,
            default => self::KindFaq,
        };
    }

    /**
     * Zipf-like popularity: the FAQ at rank r gets weight 1 / r.
     *
     * @param  list<string>  $rankedFaqIds
     * @return array<string, float>
     */
    private function popularityWeights(array $rankedFaqIds): array
    {
        $weights = [];

        foreach (array_values($rankedFaqIds) as $rank => $faqId) {
            $weights[$faqId] = 1 / ($rank + 1);
        }

        return $weights;
    }

    /**
     * @param  array<string, float>  $weights
     */
    private function weightedPick(Randomizer $randomizer, array $weights): string
    {
        $target = $randomizer->nextFloat() * array_sum($weights);

        foreach ($weights as $faqId => $weight) {
            $target -= $weight;

            if ($target <= 0) {
                return (string) $faqId;
            }
        }

        return (string) array_key_last($weights);
    }

    /**
     * @return list<int>
     */
    private function splitEvenly(int $total, int $parts): array
    {
        return array_map(
            fn (int $part): int => intdiv($total, $parts) + ($part < $total % $parts ? 1 : 0),
            range(0, $parts - 1),
        );
    }
}
