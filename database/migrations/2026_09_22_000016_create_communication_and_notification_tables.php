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
        // 1. In-App Notifications (Native Laravel schema)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 2. Email Templates
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('subject_template', 255);
            $table->longText('body_html_template');
            $table->text('body_text_template');
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->default('en');
            $table->timestamps();
        });

        // 3. Email Deliveries / Outbox Log
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('dedupe_key', 255)->unique();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->string('recipient_email', 255)->index();
            $table->string('recipient_name', 255)->nullable();
            $table->string('template_key', 100)->index();
            $table->string('subject', 255);
            $table->longText('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->string('status', 20)->default('queued')->index(); // queued, sending, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // 4. Notification Preferences
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20)->default('email'); // email, in_app
            $table->string('notification_type', 100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'channel', 'notification_type'], 'user_channel_pref_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('notifications');
    }
};
