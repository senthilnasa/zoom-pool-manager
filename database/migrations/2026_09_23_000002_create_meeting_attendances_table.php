<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->string('zoom_meeting_id', 64)->index();
            $table->string('zoom_participant_id', 64)->nullable();
            $table->string('participant_name', 255);
            $table->string('participant_email', 190)->nullable()->index();
            $table->timestamp('join_time');
            $table->timestamp('leave_time')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(100.00);
            $table->string('device_type', 50)->nullable();
            $table->string('status', 32)->default('present'); // present, partial, absent
            $table->timestamps();

            $table->index(['meeting_id', 'participant_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendances');
    }
};
