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
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'description')) {
                $table->string('description', 255)->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('roles', 'description')) {
                $columns[] = 'description';
            }
            if (Schema::hasColumn('roles', 'is_system')) {
                $columns[] = 'is_system';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
