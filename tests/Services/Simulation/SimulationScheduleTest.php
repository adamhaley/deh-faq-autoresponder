<?php

namespace Tests\Services\Simulation;

use App\Services\Simulation\SimulationSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimulationSchedule::class)]
class SimulationScheduleTest extends TestCase
{
    /**
     * @return list<array{index: int, week: int, day: string, kind: string, faq_id: ?string, variant: int, arrives_at: CarbonImmutable}>
     */
    private function schedule(int $seed = 42, int $weeks = 4, int $perWeek = 25): array
    {
        $faqIds = array_map(fn (int $i): string => "faq-{$i}", range(1, 79));

        return (new SimulationSchedule)->build($seed, $weeks, $perWeek, $faqIds, CarbonImmutable::parse('2026-01-05'));
    }

    #[Test]
    public function it_is_identical_for_the_same_seed(): void
    {
        $this->assertEquals($this->schedule(), $this->schedule());
    }

    #[Test]
    public function it_differs_for_another_seed(): void
    {
        $this->assertNotEquals($this->schedule(seed: 1), $this->schedule(seed: 2));
    }

    #[Test]
    public function it_delivers_the_requested_volume_per_week(): void
    {
        $perWeek = array_count_values(array_column($this->schedule(weeks: 3, perWeek: 25), 'week'));

        $this->assertSame([1 => 25, 2 => 25, 3 => 25], $perWeek);
    }

    #[Test]
    public function it_only_assigns_an_faq_to_faq_questions(): void
    {
        $mismatches = array_filter(
            $this->schedule(),
            fn (array $event): bool => ($event['kind'] === SimulationSchedule::KindFaq) !== ($event['faq_id'] !== null),
        );

        $this->assertSame([], $mismatches);
    }

    #[Test]
    public function it_repeats_popular_faqs(): void
    {
        $faqIds = array_filter(array_column($this->schedule(weeks: 12), 'faq_id'));

        $this->assertGreaterThan(5, max(array_count_values($faqIds)));
    }

    #[Test]
    public function it_reviews_the_morning_after_arrival(): void
    {
        $this->assertSame('2026-01-07 10:00', SimulationSchedule::reviewTimeFor('2026-01-06')->format('Y-m-d H:i'));
    }
}
