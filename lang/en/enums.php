<?php

use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionClassification;
use App\Enums\EmailQuestionReviewStatus;
use App\Enums\EmailThreadDraftStatus;
use App\Enums\FaqRetrievalStatus;
use App\Enums\GmailMailboxSyncStatus;
use App\Enums\UserRole;

return [
    AnswerDraftStatus::class => [
        'queued' => 'Queued',
        'generating' => 'Generating',
        'generation_failed' => 'Generation failed',
        'draft' => 'Draft',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs_revision' => 'Needs revision',
    ],

    EmailQuestionClassification::class => [
        'valid_faq_question' => 'Valid question',
        'noise' => 'Noise',
        'unanswerable' => 'Unanswerable',
        'needs_human' => 'Needs human',
    ],

    EmailQuestionReviewStatus::class => [
        'pending_review' => 'Pending review',
        'valid' => 'Valid question',
        'noise' => 'Noise',
        'unanswerable' => 'Unanswerable',
        'needs_human' => 'Needs human',
    ],

    EmailThreadDraftStatus::class => [
        'created' => 'Draft created',
        'updated' => 'Draft updated',
        'failed' => 'Failed',
    ],

    FaqRetrievalStatus::class => [
        'not_started' => 'Not started',
        'queued' => 'Queued',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    GmailMailboxSyncStatus::class => [
        'connected' => 'Connected',
        'failed' => 'Failed',
        'resync_required' => 'Resync required',
    ],

    UserRole::class => [
        'admin' => 'Admin',
        'reviewer' => 'Reviewer',
        'viewer' => 'Viewer',
    ],
];
