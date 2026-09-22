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
        Schema::create('zoom_connections', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 100);
            $table->text('account_id'); // encrypted
            $table->text('client_id'); // encrypted
            $table->text('client_secret'); // encrypted
            $table->text('webhook_secret_token')->nullable(); // encrypted
            $table->string('status', 30)->default('disconnected')->index(); // active, degraded, error, disconnected
            $table->json('granted_scopes')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('last_error_at')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('zoom_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('zoom_connections')->cascadeOnDelete();
            $table->string('zoom_user_id', 64)->index();
            $table->string('email', 190)->index();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->unsignedInteger('user_type')->default(2); // 1: Basic, 2: Licensed
            $table->string('status', 30)->default('active')->index(); // active, inactive, pending
            $table->string('timezone', 50)->nullable();
            $table->text('host_key')->nullable(); // encrypted
            $table->dateTime('synced_at');
            $table->json('raw_metadata')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'zoom_user_id']);
        });

        Schema::create('zoom_resources', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('zoom_user_id')->unique()->constrained('zoom_users')->cascadeOnDelete();
            $table->boolean('managed')->default(false)->index();
            $table->string('status', 30)->default('active')->index(); // active, maintenance, suspended
            $table->integer('priority')->default(10)->index();
            $table->boolean('is_backup')->default(false)->index();
            $table->unsignedInteger('participant_capacity')->default(100);
            $table->unsignedInteger('large_meeting_capacity')->default(0);
            $table->unsignedInteger('webinar_capacity')->default(0);
            $table->boolean('cloud_recording')->default(true);
            $table->boolean('transcript')->default(true);
            $table->boolean('ai_companion')->default(false);
            $table->unsignedInteger('max_concurrent')->default(1);
            $table->dateTime('capabilities_checked_at')->nullable();
            $table->unsignedInteger('daily_api_call_count')->default(0);
            $table->dateTime('daily_api_reset_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('resource_pools', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('pool_strategy', 30)->default('least_hours_today'); // round_robin, least_hours_today, least_meetings_today, priority, random
            $table->boolean('is_emergency_pool')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('resource_pool_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('resource_pools')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('zoom_resources')->cascadeOnDelete();
            $table->integer('priority')->default(10);
            $table->timestamps();

            $table->unique(['pool_id', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_pool_members');
        Schema::dropIfExists('resource_pools');
        Schema::dropIfExists('zoom_resources');
        Schema::dropIfExists('zoom_users');
        Schema::dropIfExists('zoom_connections');
    }
};
