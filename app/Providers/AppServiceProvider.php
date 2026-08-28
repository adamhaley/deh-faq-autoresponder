<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->recordActionsPosition(RecordActionsPosition::BeforeColumns);
        });

        FilamentTimezone::set(config('app.display_timezone'));

        RateLimiter::for('openai', fn (object $job): Limit => Limit::perMinute(30)->by('openai'));
        RateLimiter::for('gmail', fn (object $job): Limit => Limit::perMinute(60)->by('gmail'));

        DevCommands::artisan('schedule:work', 'schedule');
        DevCommands::artisan('reverb:start', 'reverb');
    }
}
