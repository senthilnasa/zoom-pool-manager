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
        Schema::create('overrides', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 100)->index(); // meeting, zoom_resource
            $table->unsignedBigInteger('target_id')->index();
            $table->string('field', 50)->index(); // emergency_host_start, host_key_reveal, resource_reallocation
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason'); // mandatory explanation for emergency IT override
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overrides');
    }
};
