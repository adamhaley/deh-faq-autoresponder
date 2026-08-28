<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Fixed, page-level channels rather than per-record ones -- Livewire only
 * computes and sends its `listeners` array to the frontend once, at
 * component mount (SupportEvents::dehydrate()), and never re-evaluates it
 * afterward. A channel scoped to "whichever record's modal is currently
 * open" would be null at mount (before any modal opens) and would never
 * get subscribed to later. These fixed channels are always known at mount,
 * so they can always be subscribed to; a blanket $refresh() on any event
 * re-evaluates every record's live status anyway.
 */
Broadcast::channel('email-questions-pipeline', fn ($user): bool => $user->canReviewResponses());
Broadcast::channel('email-threads-pipeline', fn ($user): bool => $user->canReviewResponses());
