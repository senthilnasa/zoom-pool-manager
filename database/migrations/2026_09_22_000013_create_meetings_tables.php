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
        Schema::create('meeting_series', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('rrule', 255);
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->date('start_date');
            $table->date('until_date')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('series_mode', 30)->default('SINGLE_RESOURCE')->index(); // SINGLE_RESOURCE, SPLIT_WHEN_NEEDED, PER_OCCURRENCE
            $table->string('status', 30)->default('active')->index();
            $table->string('zoom_meeting_id', 64)->nullable()->index();
            $table->foreignId('zoom_resource_id')->nullable()->constrained('zoom_resources')->nullOnDelete();
            $table->unsignedBigInteger('term_id')->nullable(); // V2 placeholder
            $table->string('source', 30)->default('web');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('series_id')->nullable()->constrained('meeting_series')->nullOnDelete();
            $table->unsignedInteger('occurrence_index')->default(1);
            $table->string('zoom_occurrence_id', 64)->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('meeting_type', 50)->default('meeting')->index(); // class, meeting, exam, webinar, interview
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->unsignedInteger('participant_count')->default(10);
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('meeting_templates')->nullOnDelete();
            $table->foreignId('security_profile_id')->nullable()->constrained('security_profiles')->nullOnDelete();
            $table->string('ai_companion_policy', 20)->default('DISABLED'); // DISABLED, ALLOWED, REQUIRED
            $table->string('recording_mode', 20)->default('none'); // none, cloud, local
            $table->boolean('external_participants')->default(false);
            $table->boolean('registration_enabled')->default(false);
            $table->foreignId('zoom_resource_id')->nullable()->constrained('zoom_resources')->nullOnDelete();
            $table->string('zoom_meeting_id', 64)->nullable()->index();
            $table->string('zoom_uuid', 64)->nullable();
            $table->string('join_url', 500)->nullable();
            $table->text('passcode')->nullable(); // encrypted
            $table->dateTime('link_distributed_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('idempotency_key', 64)->unique();
            $table->string('source', 30)->default('web'); // web, api, bulk_import, instant
            $table->boolean('is_detached_from_series')->default(false);
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meeting_invitees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 190);
            $table->string('name', 150)->nullable();
            $table->string('status', 30)->default('pending')->index(); // pending, accepted, declined
            $table->timestamps();
        });

        Schema::create('meeting_registrations_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->string('approval_type', 30)->default('automatic'); // automatic, manual
            $table->json('questions')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->dateTime('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_status_history');
        Schema::dropIfExists('meeting_registrations_config');
        Schema::dropIfExists('meeting_invitees');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('meeting_series');
    }
};
