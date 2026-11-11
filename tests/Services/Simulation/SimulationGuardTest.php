<?php

namespace Tests\Services\Simulation;

use App\Services\Simulation\SimulationGuard;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(SimulationGuard::class)]
class SimulationGuardTest extends TestCase
{
    public static function unsafeTargetProvider(): Generator
    {
        yield 'production environment' => ['production', 'deh_simulation'];
        yield 'staging environment' => ['staging', 'deh_simulation'];
        yield 'app database' => ['local', 'deh_faq_autoresponder'];
        yield 'production copy' => ['local', 'deh_prod_copy'];
        yield 'test database' => ['local', 'deh_faq_autoresponder_test'];
    }

    #[Test]
    #[DataProvider('unsafeTargetProvider')]
    public function it_refuses_anything_but_a_local_simulation_database(string $environment, string $database): void
    {
        $this->expectException(RuntimeException::class);

        (new SimulationGuard)->assertSafe($environment, $database);
    }

    #[Test]
    public function it_allows_a_local_simulation_database(): void
    {
        (new SimulationGuard)->assertSafe('local', 'deh_simulation_loop_on');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_refuses_to_enter_outside_the_local_environment(): void
    {
        $this->expectException(RuntimeException::class);

        (new SimulationGuard)->enter('deh_simulation');
    }

    public static function databaseProvider(): Generator
    {
        yield 'loop-on simulation' => ['deh_simulation_loop_on', true];
        yield 'app database' => ['deh_faq_autoresponder', false];
        yield 'production copy' => ['deh_prod_copy', false];
    }

    #[Test]
    #[DataProvider('databaseProvider')]
    public function it_recognises_simulation_databases(string $database, bool $expected): void
    {
        $this->assertSame($expected, SimulationGuard::isSimulationDatabase($database));
    }
}
