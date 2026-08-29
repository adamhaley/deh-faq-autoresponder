<?php

namespace Tests\Feature;

use App\Ai\Agents\EmailGreetingGenerator;
use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionReviewStatus;
use App\Enums\EmailThreadDraftStatus;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailTemplate;
use App\Models\EmailThreadDraft;
use App\Models\GmailMailbox;
use App\Models\GmailMessage;
use App\Services\EmailQuestions\EmailThreadDraftComposerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailThreadDraftComposerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_composes_and_creates_a_gmail_draft_once_the_thread_is_fully_approved(): void
    {
        EmailGreetingGenerator::fake([
            ['greeting' => 'Sehr geehrte Frau Schmidt'],
        ])->preventStrayPrompts();

        EmailTemplate::factory()->create([
            'subject' => 'Ihre Webinarfrage',
            'body' => '<p><span data-type="mergeTag" data-id="greeting">greeting</span></p><p><span data-type="mergeTag" data-id="questions">questions</span></p>',
        ]);

        $mailbox = GmailMailbox::factory()->create();
        $message = GmailMessage::factory()->for($mailbox, 'mailbox')->create([
            'thread_id' => 'thread-abc',
            'participant_name' => 'Maria Schmidt',
            'reply_to_email' => 'maria@example.com',
            'from_email' => 'webinar-platform@example.com',
            'payload' => ['headers' => [['name' => 'Message-ID', 'value' => '<original@example.com>']]],
        ]);

        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create(['question_text' => 'Wie funktioniert das Investment?']);

        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Sie investieren direkt in Farbedelsteine.',
            'status' => AnswerDraftStatus::Approved,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/users/me/drafts') && $request->method() === 'POST') {
                $this->assertSame('thread-abc', $request->data()['message']['threadId']);

                return Http::response(['id' => 'draft-123']);
            }

            return Http::response(status: 404);
        });

        $draft = app(EmailThreadDraftComposerService::class)->composeForThread('thread-abc');

        $this->assertInstanceOf(EmailThreadDraft::class, $draft);
        $this->assertSame('draft-123', $draft->gmail_draft_id);
        $this->assertSame(EmailThreadDraftStatus::Created, $draft->status);
        $this->assertSame('Ihre Webinarfrage', $draft->subject);
        $this->assertStringContainsString('Sehr geehrte Frau Schmidt', $draft->body);
        $this->assertStringContainsString('Wie funktioniert das Investment?', $draft->body);
        $this->assertStringContainsString('Sie investieren direkt in Farbedelsteine.', $draft->body);
    }

    public function test_it_updates_the_existing_gmail_draft_when_an_approved_answer_changes(): void
    {
        EmailGreetingGenerator::fake([
            ['greeting' => 'Sehr geehrte Damen und Herren'],
        ])->preventStrayPrompts();

        EmailTemplate::factory()->create();

        $mailbox = GmailMailbox::factory()->create();
        $message = GmailMessage::factory()->for($mailbox, 'mailbox')->create(['thread_id' => 'thread-xyz']);

        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create();

        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Original answer.',
            'status' => AnswerDraftStatus::Approved,
        ]);

        EmailThreadDraft::factory()->create([
            'thread_id' => 'thread-xyz',
            'gmail_mailbox_id' => $mailbox->id,
            'gmail_draft_id' => 'existing-draft-id',
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/users/me/drafts/existing-draft-id') && $request->method() === 'PUT') {
                return Http::response(['id' => 'existing-draft-id']);
            }

            return Http::response(status: 404);
        });

        $draft = app(EmailThreadDraftComposerService::class)->composeForThread('thread-xyz');

        $this->assertSame('existing-draft-id', $draft->gmail_draft_id);
        $this->assertSame(EmailThreadDraftStatus::Updated, $draft->status);
        $this->assertSame(1, EmailThreadDraft::query()->count());
    }

    public function test_it_does_nothing_when_the_thread_is_not_fully_approved(): void
    {
        EmailTemplate::factory()->create();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-incomplete']);

        $approvedQuestion = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $approvedQuestion->id,
            'status' => AnswerDraftStatus::Approved,
        ]);

        $pendingQuestion = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $pendingQuestion->id,
            'status' => AnswerDraftStatus::Draft,
        ]);

        Http::preventStrayRequests();

        $draft = app(EmailThreadDraftComposerService::class)->composeForThread('thread-incomplete');

        $this->assertNull($draft);
        $this->assertSame(0, EmailThreadDraft::query()->count());
    }
}
