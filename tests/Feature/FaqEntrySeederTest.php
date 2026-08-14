<?php

namespace Tests\Feature;

use App\Models\FaqEntry;
use Database\Seeders\FaqEntrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FaqEntrySeeder::class)]
class FaqEntrySeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_canonical_faq_entries_with_embeddings(): void
    {
        $this->seed(FaqEntrySeeder::class);

        $this->assertSame(79, FaqEntry::query()->count());
        $this->assertSame(79, FaqEntry::query()->whereNotNull('embedding')->count());
    }

    #[Test]
    public function it_is_idempotent_when_run_more_than_once(): void
    {
        $this->seed(FaqEntrySeeder::class);
        $this->seed(FaqEntrySeeder::class);

        $this->assertSame(79, FaqEntry::query()->count());
    }
}
