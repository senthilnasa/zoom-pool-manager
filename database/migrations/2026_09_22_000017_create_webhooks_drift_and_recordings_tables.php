<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Webhook events log
        Schema::create('zoom_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('connection_id')->nullable()->constrained('zoom_connections')->nullOnDelete();
            $table->string('event_id', 190)->unique(); // Zoom event id or computed dedupe hash
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->boolean('signature_valid')->default(true);
            $table->string('status', 30)->default('pending')->index(); // pending, processed, failed, ignored
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['event_type', 'status']);
            $table->index('created_at');
        });

        // 2. Drift incidents / conflicts
        Schema::create('drift_conflicts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('incident_type', 64)->index(); // time_mismatch, deleted_on_zoom, unmanaged_external_meeting, license_changed, passcode_mismatch, host_mismatch, settings_mismatch
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->foreignId('zoom_resource_id')->nullable()->constrained('zoom_resources')->nullOnDelete();
            $table->string('zoom_meeting_id', 64)->nullable()->index();
            $table->json('details');
            $table->string('status', 30)->default('open')->index(); // open, resolved, accepted_zoom, ignored, marked_external
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        // 3. Cloud recordings
        Schema::create('cloud_recordings', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->foreignId('zoom_resource_id')->nullable()->constrained('zoom_resources')->nullOnDelete();
            $table->foreignId('logical_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('zoom_meeting_id', 64)->index();
            $table->string('zoom_recording_id', 64)->nullable()->index();
            $table->string('topic')->nullable();
            $table->string('storage_provider', 32)->default('zoom');
            $table->timestamp('recording_start')->nullable();
            $table->timestamp('recording_end')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->text('share_url')->nullable();
            $table->text('play_url')->nullable();
            $table->text('download_url')->nullable();
            $table->text('passcode')->nullable(); // encrypted
            $table->string('status', 32)->default('completed')->index(); // processing, completed, failed, deleted
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['logical_owner_user_id', 'status']);
        });

        // 4. Recording individual media files (MP4, M4A, CHAT, TIMELINE)
        Schema::create('recording_files', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('recording_id')->constrained('cloud_recordings')->cascadeOnDelete();
            $table->string('zoom_file_id', 64)->nullable()->index();
            $table->string('file_type', 32)->default('MP4');
            $table->string('file_extension', 16)->default('mp4');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->text('play_url')->nullable();
            $table->text('download_url')->nullable();
            $table->string('status', 32)->default('completed');
            $table->timestamps();
        });

        // 5. Recording access logs
        Schema::create('recording_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained('cloud_recordings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32)->default('view'); // view, play_redirect, download
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recording_access_logs');
        Schema::dropIfExists('recording_files');
        Schema::dropIfExists('cloud_recordings');
        Schema::dropIfExists('drift_conflicts');
        Schema::dropIfExists('zoom_webhook_events');
    }
};
