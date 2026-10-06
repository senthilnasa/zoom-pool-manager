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
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('auto_send_attendance')->default(false)->after('attendance_tracking');
            $table->boolean('auto_send_recording')->default(false)->after('auto_send_attendance');
        });

        Schema::table('meeting_series', function (Blueprint $table) {
            $table->boolean('auto_send_attendance')->default(false)->after('attendance_tracking');
            $table->boolean('auto_send_recording')->default(false)->after('auto_send_attendance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_send_attendance',
                'auto_send_recording',
            ]);
        });

        Schema::table('meeting_series', function (Blueprint $table) {
            $table->dropColumn([
                'auto_send_attendance',
                'auto_send_recording',
            ]);
        });
    }
};
