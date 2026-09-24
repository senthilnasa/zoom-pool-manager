<?php

use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\RecordingController;
use App\Http\Controllers\Api\V1\ResourcePoolController;
use App\Http\Controllers\Api\ZoomSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Zoom Pool Manager (ZPM)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware(['api', 'api.auth', 'idempotent'])->group(function () {
    // Resource Pool Availability
    Route::get('/availability', [AvailabilityController::class, 'check'])
        ->middleware('api.scope:availability:read')
        ->name('api.v1.availability');

    // Meetings
    Route::get('/meetings', [MeetingController::class, 'index'])
        ->middleware('api.scope:meetings:read')
        ->name('api.v1.meetings.index');

    Route::post('/meetings', [MeetingController::class, 'store'])
        ->middleware('api.scope:meetings:write')
        ->name('api.v1.meetings.store');

    Route::get('/meetings/{publicId}', [MeetingController::class, 'show'])
        ->middleware('api.scope:meetings:read')
        ->name('api.v1.meetings.show');

    Route::post('/meetings/{publicId}/cancel', [MeetingController::class, 'cancel'])
        ->middleware('api.scope:meetings:write')
        ->name('api.v1.meetings.cancel');

    // Resource Pools
    Route::get('/pools', [ResourcePoolController::class, 'index'])
        ->middleware('api.scope:pools:read')
        ->name('api.v1.pools.index');

    // Cloud Recordings
    Route::get('/recordings', [RecordingController::class, 'index'])
        ->middleware('api.scope:recordings:read')
        ->name('api.v1.recordings.index');

    Route::get('/recordings/{publicId}', [RecordingController::class, 'show'])
        ->middleware('api.scope:recordings:read')
        ->name('api.v1.recordings.show');

    // Zoom Settings
    Route::get('/settings/zoom', [ZoomSettingsController::class, 'show'])
        ->middleware('api.scope:admin')
        ->name('api.v1.settings.zoom.show');
    Route::post('/settings/zoom', [ZoomSettingsController::class, 'update'])
        ->middleware('api.scope:admin')
        ->name('api.v1.settings.zoom.update');
    Route::post('/settings/zoom/test', [ZoomSettingsController::class, 'test'])
        ->middleware('api.scope:admin')
        ->name('api.v1.settings.zoom.test');
});
