<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gmail:sync-mailboxes')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('email-questions:extract')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('email-questions:classify')
    ->everyMinute()
    ->withoutOverlapping(10);
