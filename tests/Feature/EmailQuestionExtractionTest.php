<?php

namespace Tests\Feature;

use App\Models\EmailQuestion;
use App\Models\GmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailQuestionExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_extracts_chat_questions_from_imported_gmail_messages(): void
    {
        GmailMessage::factory()->create([
            'text_body' => <<<'TEXT'
Vorname: Tatjana
Nachname: Beispiel
E-Mail: tatjana@example.com

Chat:
1: Tatjana (10/09/2025 10:58): Wie sicher sind die Edelsteine verwahrt?<br>
2: Tatjana (10/09/2025 11:01): Kann ich die Steine später wieder verkaufen?
3: System (10/09/2025 11:02): Vielen Dank und beste Grüße
TEXT,
        ]);

        $this->artisan('email-questions:extract')
            ->expectsOutput('Extracted 2 email question(s).')
            ->assertSuccessful();

        $this->assertSame(2, EmailQuestion::query()->count());

        $questions = EmailQuestion::query()->orderBy('question_order')->pluck('question_text')->all();

        $this->assertSame([
            'Wie sicher sind die Edelsteine verwahrt?',
            'Kann ich die Steine später wieder verkaufen?',
        ], $questions);
    }

    public function test_extraction_is_idempotent_per_message_and_question_text(): void
    {
        GmailMessage::factory()->create([
            'text_body' => <<<'TEXT'
Chat:
1: Max (10/09/2025 10:58): Was kostet eine Beratung?
TEXT,
        ]);

        $this->artisan('email-questions:extract')->assertSuccessful();
        $this->artisan('email-questions:extract')->assertSuccessful();

        $this->assertSame(1, EmailQuestion::query()->count());
    }

    public function test_command_extracts_plain_body_question_when_no_chat_section_exists(): void
    {
        GmailMessage::factory()->create([
            'text_body' => 'Können Sie mir erklären, wie die Wertgutachten erstellt werden?',
        ]);

        $this->artisan('email-questions:extract')
            ->expectsOutput('Extracted 1 email question(s).')
            ->assertSuccessful();

        $question = EmailQuestion::query()->firstOrFail();

        $this->assertSame('Können Sie mir erklären, wie die Wertgutachten erstellt werden?', $question->question_text);
        $this->assertSame('body_fallback', $question->extraction_metadata['source']);
    }

    public function test_plain_body_fallback_ignores_url_query_strings_in_long_notifications(): void
    {
        GmailMessage::factory()->create([
            'text_body' => str_repeat(
                'View job: https://www.linkedin.com/jobs/view/123?trackingId=abc&refId=def ',
                20,
            ),
        ]);

        $this->artisan('email-questions:extract')
            ->expectsOutput('Extracted 0 email question(s).')
            ->assertSuccessful();

        $this->assertSame(0, EmailQuestion::query()->count());
    }

    public function test_command_extracts_webinaris_chat_questions_from_html_body_before_footer(): void
    {
        GmailMessage::factory()->create([
            'text_body' => null,
            'html_body' => <<<'HTML'
<p>Nachfolgend findest du die Chatnachrichten.<br><br>
Chat:<br>
1: Gabriele Stangl  (13.08.2026 10:47): können auch Aktien z.b. Allianz als Vermögenswert in Atien der Bank umgewandet werden nahc dem SAG<br/><br/><br/><br><br>
Viele Grüße,<br><br>
Webinaris (13.08.2026 11:42)<br><br>
Hinweis: Alle Zeiten sind in der Zeitzone UTC+2 angegeben.</p>
HTML,
        ]);

        $this->artisan('email-questions:extract')
            ->expectsOutput('Extracted 1 email question(s).')
            ->assertSuccessful();

        $question = EmailQuestion::query()->firstOrFail();

        $this->assertSame(
            'können auch Aktien z.b. Allianz als Vermögenswert in Atien der Bank umgewandet werden nahc dem SAG',
            $question->question_text,
        );
        $this->assertSame('chat_section', $question->extraction_metadata['source']);
    }
}
