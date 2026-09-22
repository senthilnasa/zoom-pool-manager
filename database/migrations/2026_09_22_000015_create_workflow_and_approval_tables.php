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
        // 1. Workflow Rules
        Schema::create('workflow_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->integer('priority')->default(100)->index();
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        // 2. Workflow Executions (Audit & Log)
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workflow_rule_id')->nullable()->constrained('workflow_rules')->nullOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->boolean('matched')->default(false);
            $table->json('actions_triggered')->nullable();
            $table->json('logs')->nullable();
            $table->timestamps();
        });

        // 3. Meeting Approvals (Multi-step tracking)
        Schema::create('meeting_approvals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->unsignedInteger('step')->default(1);
            $table->foreignId('approver_user_id')->constrained('users');
            $table->foreignId('delegated_from_user_id')->nullable()->constrained('users');
            $table->string('decision', 20)->default('pending')->index(); // pending, approved, rejected, bypassed
            $table->text('decision_notes')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'step', 'decision']);
        });

        // 4. Approval Delegations
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'starts_at', 'ends_at']);
        });

        // 5. Quotas
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('scope_type', 20)->index(); // 'user', 'department'
            $table->unsignedBigInteger('scope_id')->index();
            $table->unsignedInteger('max_meetings_per_month')->nullable();
            $table->unsignedInteger('max_hours_per_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id']);
        });

        // 6. Quota Usages
        Schema::create('quota_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quota_id')->constrained('quotas')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedInteger('meetings_count')->default(0);
            $table->unsignedInteger('minutes_used')->default(0);
            $table->timestamps();

            $table->unique(['quota_id', 'period_year', 'period_month']);
        });

        // 7. Waitlist Entries
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->string('status', 20)->default('waiting')->index(); // waiting, allocated, expired, cancelled
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('allocated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('quota_usages');
        Schema::dropIfExists('quotas');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('meeting_approvals');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflow_rules');
    }
};
