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
        Schema::table('email_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('email_templates', 'category')) {
                $table->string('category', 50)->nullable()->after('key');
            }
            if (! Schema::hasColumn('email_templates', 'description')) {
                $table->string('description', 255)->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            if (Schema::hasColumn('email_templates', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('email_templates', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
