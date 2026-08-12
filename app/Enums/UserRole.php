<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Reviewer = 'reviewer';
    case Viewer = 'viewer';

    public function canReviewResponses(): bool
    {
        return $this !== self::Viewer;
    }
}
