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
        // 1. Alerts & Anomaly Monitor (SPEC Part H12)
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('key', 100)->index();
            $table->string('severity', 20)->default('warning')->index(); // info, warning, critical
            $table->string('title', 190);
            $table->text('message');
            $table->json('details')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->unsignedInteger('count')->default(1);
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['key', 'resolved_at']);
        });

        // 2. Database Backup Records (SPEC Part H12)
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('filename', 190);
            $table->string('file_path', 255);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('storage_disk', 50)->default('local');
            $table->string('status', 30)->default('pending')->index(); // pending, completed, failed
            $table->boolean('is_encrypted')->default(false);
            $table->string('checksum', 64)->nullable(); // SHA-256 checksum
            $table->dateTime('verified_at')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        // 3. User Data Export / Privacy Requests (SPEC Part H12 / DPDP & GDPR)
        Schema::create('data_export_requests', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index(); // pending, completed, failed
            $table->string('file_path', 255)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_export_requests');
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('alerts');
    }
};
