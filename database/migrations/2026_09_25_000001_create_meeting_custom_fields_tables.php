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
        if (! Schema::hasTable('meeting_custom_fields')) {
            Schema::create('meeting_custom_fields', function (Blueprint $table) {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->string('name', 100);
                $table->string('field_key', 50)->unique();
                $table->string('field_type', 30)->default('text'); // text, textarea, dropdown, int
                $table->json('options')->nullable(); // array of string options for dropdown
                $table->string('placeholder', 150)->nullable();
                $table->string('help_text', 255)->nullable();
                $table->string('default_value', 255)->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('cancelled_reason');
            }
        });

        Schema::table('meeting_series', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_series', 'custom_fields')) {
                $table->json('custom_fields')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_series', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_series', 'custom_fields')) {
                $table->dropColumn('custom_fields');
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'custom_fields')) {
                $table->dropColumn('custom_fields');
            }
        });

        Schema::dropIfExists('meeting_custom_fields');
    }
};
