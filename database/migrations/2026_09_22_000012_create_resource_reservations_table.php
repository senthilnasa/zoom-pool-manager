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
        Schema::create('resource_reservations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('resource_id')->constrained('zoom_resources')->cascadeOnDelete();
            $table->unsignedBigInteger('meeting_id')->nullable()->index();
            $table->string('source', 30)->default('zpm')->index(); // zpm, external, maintenance
            $table->dateTime('occupied_from')->index();
            $table->dateTime('occupied_until')->index(); // Strictly includes buffer
            $table->string('status', 30)->default('held')->index(); // held, confirmed, released
            $table->dateTime('hold_expires_at')->nullable()->index();
            $table->timestamps();

            // Single source of truth index for high-concurrency overlap checks
            $table->index(['resource_id', 'occupied_from', 'occupied_until', 'status'], 'idx_resource_occupancy_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_reservations');
    }
};
