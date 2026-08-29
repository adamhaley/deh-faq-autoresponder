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
        'queued' => 'In Warteschlange',
        'generating' => 'Wird generiert',
        'generation_failed' => 'Generierung fehlgeschlagen',
        'draft' => 'Entwurf',
        'approved' => 'Freigegeben',
        'rejected' => 'Abgelehnt',
        'needs_revision' => 'Überarbeitung nötig',
    ],

    EmailQuestionClassification::class => [
        'valid_faq_question' => 'Gültige Frage',
        'noise' => 'Rauschen',
        'unanswerable' => 'Nicht beantwortbar',
        'needs_human' => 'Menschliche Prüfung nötig',
    ],

    EmailQuestionReviewStatus::class => [
        'pending_review' => 'Prüfung ausstehend',
        'valid' => 'Gültige Frage',
        'noise' => 'Rauschen',
        'unanswerable' => 'Nicht beantwortbar',
        'needs_human' => 'Menschliche Prüfung nötig',
    ],

    EmailThreadDraftStatus::class => [
        'created' => 'Entwurf erstellt',
        'updated' => 'Entwurf aktualisiert',
        'failed' => 'Fehlgeschlagen',
    ],

    FaqRetrievalStatus::class => [
        'not_started' => 'Nicht gestartet',
        'queued' => 'In Warteschlange',
        'processing' => 'In Bearbeitung',
        'completed' => 'Abgeschlossen',
        'failed' => 'Fehlgeschlagen',
    ],

    GmailMailboxSyncStatus::class => [
        'connected' => 'Verbunden',
        'failed' => 'Fehlgeschlagen',
        'resync_required' => 'Neue Synchronisierung nötig',
    ],

    UserRole::class => [
        'admin' => 'Admin',
        'reviewer' => 'Prüfer',
        'viewer' => 'Betrachter',
    ],
];
