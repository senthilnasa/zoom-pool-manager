<?php

namespace App\Console\Commands;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAdminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:admin:reset 
                            {email : The email address of the administrator}
                            {--password= : Optional new password (generated automatically if omitted)}
                            {--reset-mfa : Reset and disable MFA enrollment for this account}
                            {--reason= : Mandatory reason for emergency audit record}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Break-glass emergency password and MFA reset for local administrators';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        $newPassword = $this->option('password') ?: Str::password(16);
        $reason = $this->option('reason') ?: $this->ask('Please enter the reason for this emergency break-glass action');

        if (empty($reason)) {
            $this->error('A reason is mandatory for emergency break-glass auditing.');

            return self::FAILURE;
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        $mfaReset = false;
        if ($this->option('reset-mfa')) {
            $user->mfa_enabled = false;
            $user->save();
            $user->mfaSecret()->delete();
            $user->mfaRecoveryCodes()->delete();
            $mfaReset = true;
        }

        // Record immutable audit entry
        AuditLog::create([
            'actor_user_id' => null, // CLI invocation
            'event' => 'admin.break_glass_reset',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => ['email' => $user->email],
            'new_values' => [
                'password_reset' => true,
                'mfa_reset' => $mfaReset,
                'reason' => $reason,
            ],
            'ip_address' => '127.0.0.1 (CLI)',
            'user_agent' => 'Artisan CLI / zpm:admin:reset',
        ]);

        $this->info("Successfully reset password for [{$email}].");
        $this->line("New Password: <comment>{$newPassword}</comment>");
        if ($mfaReset) {
            $this->warn('MFA has been reset. User will be required to re-enroll upon next login.');
        }

        return self::SUCCESS;
    }
}
