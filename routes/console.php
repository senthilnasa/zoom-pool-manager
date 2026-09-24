<?php

use App\Domain\Settings\Services\ScheduledJobService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dynamically registered background scheduled jobs (configured via Settings -> Scheduled Jobs)
app(ScheduledJobService::class)->applySchedule();
