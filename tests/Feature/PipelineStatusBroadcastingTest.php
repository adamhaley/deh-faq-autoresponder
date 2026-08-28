<?php

namespace Tests\Feature;

use App\Ai\Agents\EmailGreetingGenerator;
use App\Ai\Agents\EmailQuestionAnswerGenerator;
use App\Enums\UserRole;
use App\Events\RecordPipelineStatusChanged;
use App\Jobs\ComposeEmailThreadDraft;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Jobs\RetrieveEmailQuestionFaqMatches;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailQuestionFaqMatch;
use App\Models\EmailTemplate;
use App\Models\FaqEntry;
use App\Models\GmailMessage;
use App\Models\User;
use App\Services\EmailQuestions\EmailQuestionAnswerDraftGenerationService;
use App\Services\EmailQuestions\EmailQuestionFaqRetrievalService;
use App\Services\EmailQuestions\EmailThreadDraftComposerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class PipelineStatusBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_retrieval_broadcasts_status_changes_for_its_email_question_channel(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);
        Queue::fake([GenerateEmailQuestionAnswerDraft::class]);

        Embeddings::fake([[array_pad([1.0], 1536, 0.0)]]);

        FaqEntry::factory()->create(['embedding' => '['.implode(',', array_pad([1.0], 1536, 0.0)).']']);

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create();

        (new RetrieveEmailQuestionFaqMatches($question->id))
            ->handle(app(EmailQuestionFaqRetrievalService::class));

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === "email-questions.{$question->id}",
        );
    }

    public function test_answer_draft_generation_broadcasts_status_changes_for_its_email_question_channel(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);

        EmailQuestionAnswerGenerator::fake([
            ['answer' => 'Answer.', 'reason' => 'Test reason.'],
        ])->preventStrayPrompts();

        $question = EmailQuestion::factory()->create();
        EmailQuestionFaqMatch::factory()->create(['email_question_id' => $question->id, 'rank' => 1]);

        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => EmailQuestionAnswerDraft::StatusQueued,
        ]);

        (new GenerateEmailQuestionAnswerDraft($question->id))
            ->handle(app(EmailQuestionAnswerDraftGenerationService::class));

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === "email-questions.{$question->id}",
        );
    }

    public function test_thread_draft_composition_broadcasts_status_changes_for_its_thread_channel(): void
    {
        Event::fake([RecordPipelineStatusChanged::class]);

        // Never let this test's job hit the real Gmail API and create a
        // real draft -- fake every outbound HTTP call and fail loudly on
        // anything unfaked, mirroring EmailThreadDraftComposerServiceTest.
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/users/me/drafts') && $request->method() === 'POST') {
                return Http::response(['id' => 'draft-broadcast-test']);
            }

            return Http::response(status: 404);
        });

        EmailGreetingGenerator::fake([
            ['greeting' => 'Hello'],
        ])->preventStrayPrompts();

        EmailTemplate::factory()->create();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-broadcast']);
        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create();

        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Answer.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
        ]);

        (new ComposeEmailThreadDraft('thread-broadcast'))
            ->handle(app(EmailThreadDraftComposerService::class));

        Event::assertDispatched(
            RecordPipelineStatusChanged::class,
            fn (RecordPipelineStatusChanged $event): bool => $event->channel === 'email-threads.thread-broadcast',
        );
    }

    public function test_admin_and_reviewer_can_authorize_the_pipeline_channels_but_viewer_cannot(): void
    {
        // phpunit.xml forces BROADCAST_CONNECTION=null suite-wide so no test
        // can accidentally broadcast for real; NullBroadcaster::auth() is a
        // total no-op that never runs channel closures at all, so this one
        // test needs a broadcaster that actually performs the authorization
        // check (Reverb speaks Pusher's protocol; auth() is pure local
        // signature logic, no live socket connection required to run it).
        // Broadcast::channel() registers onto whichever broadcaster is
        // default at the time routes/channels.php loads, so switching the
        // default here requires re-requiring it to register the closures
        // onto the freshly-resolved instance too.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($admin)
            ->postJson('/broadcasting/auth', ['channel_name' => 'private-email-questions.1', 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs($reviewer)
            ->postJson('/broadcasting/auth', ['channel_name' => 'private-email-questions.1', 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson('/broadcasting/auth', ['channel_name' => 'private-email-questions.1', 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }
}
