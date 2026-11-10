<?php

namespace Tests\Config;

use Laravel\Ai\AiManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversNothing]
class AiConfigTest extends TestCase
{
    #[Test]
    public function it_drafts_with_the_pinned_openai_text_model(): void
    {
        $this->assertSame('gpt-5.4', app(AiManager::class)->textProvider('openai')->defaultTextModel());
    }

    #[Test]
    public function it_drafts_with_the_configured_text_model(): void
    {
        config(['ai.providers.openai.models.text.default' => 'gpt-5-mini']);

        $this->assertSame('gpt-5-mini', app(AiManager::class)->textProvider('openai')->defaultTextModel());
    }
}
