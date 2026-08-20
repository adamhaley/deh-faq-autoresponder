<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(EmailTemplateSeeder::class)]
class EmailTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_default_template_when_missing(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::query()->where('name', 'Default')->sole();

        $this->assertSame('Deutsches Edelsteinhaus Sachwerte - Ihre Webinarfrage', $template->subject);
        $this->assertNotSame('', $template->body);
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_default_template(): void
    {
        EmailTemplate::query()->create([
            'name' => 'Default',
            'subject' => 'Edited subject',
            'body' => '<p>Edited body</p>',
        ]);

        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::sole();

        $this->assertSame('Edited subject', $template->subject);
        $this->assertSame('<p>Edited body</p>', $template->body);
    }

    #[Test]
    public function it_does_not_create_a_duplicate_when_the_template_was_renamed(): void
    {
        EmailTemplate::query()->create([
            'name' => 'Default Email',
            'subject' => 'Edited subject',
            'body' => '<p>Edited body</p>',
        ]);

        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::sole();

        $this->assertSame('Default Email', $template->name);
        $this->assertSame('Edited subject', $template->subject);
    }
}
