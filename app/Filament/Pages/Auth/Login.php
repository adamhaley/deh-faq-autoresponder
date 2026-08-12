<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    protected function getFormActions(): array
    {
        return [
            Action::make('google')
                ->label('Continue with Google')
                ->url(route('auth.google.redirect'))
                ->color('gray'),
            ...parent::getFormActions(),
        ];
    }
}
