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
        Schema::create('identity_providers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 100);
            $table->string('driver', 50); // google, microsoft, saml
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable(); // encrypted
            $table->string('tenant_id', 255)->nullable();
            $table->string('metadata_url', 500)->nullable();
            $table->mediumText('metadata_xml')->nullable();
            $table->text('certificate_primary')->nullable();
            $table->text('certificate_secondary')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->json('role_mapping')->nullable();
            $table->json('department_mapping')->nullable();
            $table->boolean('enabled')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('identity_provider_id')->constrained('identity_providers')->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('email', 190);
            $table->dateTime('last_authenticated_at')->nullable();
            $table->timestamps();

            $table->unique(['identity_provider_id', 'external_id']);
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->boolean('was_successful')->index();
            $table->string('failure_reason', 100)->nullable();
            $table->dateTime('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('user_identities');
        Schema::dropIfExists('identity_providers');
    }
};
