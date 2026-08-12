<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'role', 'is_active'])]
class AuthorizedEmail extends Model
{
    protected function casts(): array
    {
        return [
            'email' => 'string',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
