<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Integrations\GmailMailboxController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware('auth')
    ->prefix('integrations/gmail')
    ->name('integrations.gmail.')
    ->group(function (): void {
        Route::get('/redirect', [GmailMailboxController::class, 'redirect'])->name('redirect');
        Route::get('/callback', [GmailMailboxController::class, 'callback'])->name('callback');
    });
