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
        Schema::create('security_profiles', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->json('settings'); // passcode, waiting_room, join_before_host, mute_upon_entry, etc.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('booking_policies', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 100);
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->unsignedInteger('min_notice_hours')->default(2);
            $table->unsignedInteger('max_advance_days')->default(90);
            $table->unsignedInteger('min_buffer_minutes')->default(10);
            $table->unsignedInteger('default_buffer_minutes')->default(10);
            $table->unsignedInteger('max_duration_minutes')->default(180);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('blackout_periods', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 150);
            $table->string('type', 50)->default('holiday')->index(); // holiday, exam, maintenance
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_templates', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->foreignId('security_profile_id')->nullable()->constrained('security_profiles')->nullOnDelete();
            $table->foreignId('default_pool_id')->nullable()->constrained('resource_pools')->nullOnDelete();
            $table->unsignedInteger('default_duration_minutes')->default(60);
            $table->unsignedInteger('max_duration_minutes')->default(180);
            $table->unsignedInteger('max_participants')->default(100);
            $table->boolean('requires_approval')->default(false);
            $table->string('recording_mode', 20)->default('none');
            $table->string('ai_companion_policy', 20)->default('DISABLED');
            $table->string('series_mode', 30)->default('SINGLE_RESOURCE');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_templates');
        Schema::dropIfExists('blackout_periods');
        Schema::dropIfExists('booking_policies');
        Schema::dropIfExists('security_profiles');
    }
};
