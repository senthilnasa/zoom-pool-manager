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
            $table->boolean('waiting_room')->default(true)->after('recording_mode');
            $table->boolean('join_before_host')->default(false)->after('waiting_room');
            $table->unsignedSmallInteger('jbh_time')->default(0)->after('join_before_host');
            $table->boolean('attendance_tracking')->default(true)->after('jbh_time');
            $table->boolean('share_host_key')->default(false)->after('attendance_tracking');
            $table->string('host_key', 20)->nullable()->after('passcode');
        });

        Schema::table('meeting_series', function (Blueprint $table) {
            $table->string('recording_mode', 20)->default('none')->after('series_mode');
            $table->boolean('waiting_room')->default(true)->after('recording_mode');
            $table->boolean('join_before_host')->default(false)->after('waiting_room');
            $table->unsignedSmallInteger('jbh_time')->default(0)->after('join_before_host');
            $table->boolean('attendance_tracking')->default(true)->after('jbh_time');
            $table->boolean('share_host_key')->default(false)->after('attendance_tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'waiting_room',
                'join_before_host',
                'jbh_time',
                'attendance_tracking',
                'share_host_key',
                'host_key',
            ]);
        });

        Schema::table('meeting_series', function (Blueprint $table) {
            $table->dropColumn([
                'recording_mode',
                'waiting_room',
                'join_before_host',
                'jbh_time',
                'attendance_tracking',
                'share_host_key',
            ]);
        });
    }
};
