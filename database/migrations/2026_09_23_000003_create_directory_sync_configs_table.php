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
        Schema::create('directory_sync_configs', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name', 150);
            $table->string('provider_type', 50); // microsoft_entra, google_workspace, ldap_active_directory
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sync_interval_minutes')->default(60);

            // Microsoft Entra ID / Google Credentials (encrypted)
            $table->string('tenant_id', 255)->nullable();
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable();
            $table->text('service_account_json')->nullable();
            $table->string('admin_email', 190)->nullable();

            // LDAP / On-Premise AD Credentials (encrypted)
            $table->string('ldap_host', 255)->nullable();
            $table->integer('ldap_port')->nullable()->default(389);
            $table->string('ldap_base_dn', 255)->nullable();
            $table->string('ldap_bind_dn', 255)->nullable();
            $table->text('ldap_bind_password')->nullable();
            $table->boolean('ldap_use_ssl')->default(false);

            // Sync Rules & Filters
            $table->string('domain_filter', 255)->nullable();
            $table->string('group_filter', 255)->nullable();
            $table->string('default_role', 50)->default('Standard User');
            $table->foreignId('default_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('auto_create_departments')->default(true);
            $table->boolean('deactivate_missing_users')->default(false);

            // Telemetry & Status
            $table->dateTime('last_synced_at')->nullable();
            $table->string('last_sync_status', 30)->default('idle'); // idle, success, failed, running
            $table->text('last_sync_message')->nullable();
            $table->json('last_sync_stats')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directory_sync_configs');
    }
};
