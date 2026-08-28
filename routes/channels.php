<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('email-questions.{emailQuestionId}', fn ($user): bool => $user->canReviewResponses());
Broadcast::channel('email-threads.{threadId}', fn ($user): bool => $user->canReviewResponses());
