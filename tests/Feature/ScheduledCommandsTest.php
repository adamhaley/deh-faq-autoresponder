<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScheduledCommandsTest extends TestCase
{
    public function test_ingestion_pipeline_commands_are_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('gmail:sync-mailboxes')
            ->expectsOutputToContain('email-questions:extract')
            ->expectsOutputToContain('email-questions:classify')
            ->assertSuccessful();
    }
}
