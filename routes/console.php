<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Periodic Meeting Reminders (M9)
Schedule::command('zpm:notifications:send-reminders')->everyFiveMinutes();

// Periodic Overdue Approvals Escalation (M8)
Schedule::command('zpm:workflow:check-approvals')->everyFifteenMinutes();

// Hourly Drift Reconciliation Pass (M10 - SPEC Part F7)
Schedule::command('zpm:reconcile:drift')->hourly();

// Operations Heartbeat & Anomaly Check (M11 - SPEC Part H12)
Schedule::command('zpm:health:check')->everyTenMinutes();

// Daily Automated Database Backup (M11 - SPEC Part H12)
Schedule::command('zpm:backup:run')->dailyAt('02:00');
