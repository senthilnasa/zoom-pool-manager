<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zoom returns two different values for a protected recording:
     *  - `password`                : the human passcode viewers type in the prompt
     *  - `recording_play_passcode` : an encoded token appended as `?pwd=` for 1-click access
     * They were previously conflated into the single `passcode` column.
     */
    public function up(): void
    {
        Schema::table('cloud_recordings', function (Blueprint $table) {
            $table->text('play_passcode')->nullable()->after('passcode'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('cloud_recordings', function (Blueprint $table) {
            $table->dropColumn('play_passcode');
        });
    }
};
