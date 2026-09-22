<?php

namespace App\Http\Controllers\Installer;

use App\Domain\Installer\Services\InstallerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallerController extends Controller
{
    public function __construct(
        protected InstallerService $installerService
    ) {}

    /**
     * Step 1: Welcome & EULA.
     */
    public function welcome(): View
    {
        return view('installer.welcome');
    }

    /**
     * Step 2: System requirements and directory permissions.
     */
    public function requirements(): View
    {
        $requirements = $this->installerService->checkRequirements();

        return view('installer.requirements', compact('requirements'));
    }

    /**
     * Step 3: Database configuration and connection test.
     */
    public function database(): View
    {
        return view('installer.database');
    }

    /**
     * Live AJAX test of database connection.
     */
    public function testDatabase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $result = $this->installerService->testDatabaseConnection($validated);

        return response()->json($result);
    }

    /**
     * Save database config and execute migrations.
     */
    public function saveDatabase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $test = $this->installerService->testDatabaseConnection($validated);
        if (! ($test['success'] ?? false)) {
            return back()->withInput()->withErrors(['database' => $test['message']]);
        }

        $this->installerService->saveDatabaseConfig($validated);

        // Run migrations
        $migrationResult = $this->installerService->runMigrations();
        if (! ($migrationResult['success'] ?? false)) {
            return back()->withInput()->withErrors(['database' => 'Migration failed: '.($migrationResult['error'] ?? 'Unknown error')]);
        }

        return redirect()->route('installer.admin');
    }

    /**
     * Step 4: First Super Admin with Mandatory TOTP Enrollment.
     */
    public function admin(): View
    {
        return view('installer.admin');
    }

    /**
     * Generate MFA setup data (secret, QR code, recovery codes) via AJAX.
     */
    public function generateMfa(Request $request): JsonResponse
    {
        $email = $request->query('email', 'admin@example.edu');
        $mfaData = $this->installerService->generateMfaSetup($email);

        return response()->json($mfaData);
    }

    /**
     * Save Super Admin account.
     */
    public function saveAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:10|confirmed',
            'mfa_secret' => 'required|string',
            'totp_code' => 'required|string|size:6',
            'recovery_codes' => 'required|array|min:10',
        ]);

        // Verify the user correctly enrolled their TOTP authenticator
        if (! $this->installerService->verifyTotp($validated['mfa_secret'], $validated['totp_code'])) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'totp_code']))
                ->withErrors(['totp_code' => 'Invalid TOTP code. Scan the QR code with your authenticator app and enter the 6-digit code.']);
        }

        $this->installerService->createSuperAdmin($validated);

        return redirect()->route('installer.organization');
    }

    /**
     * Step 5: Organization settings.
     */
    public function organization(): View
    {
        return view('installer.organization');
    }

    /**
     * Save organization settings and finish.
     */
    public function saveOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => 'required|string|max:150',
            'timezone' => 'required|string',
            'buffer_minutes' => 'required|integer|between:10,60',
        ]);

        $this->installerService->saveOrganizationSettings($validated);
        $this->installerService->completeInstallation();

        return redirect()->route('installer.finish');
    }

    /**
     * Step 6: Installation complete.
     */
    public function finish(): View
    {
        return view('installer.finish');
    }
}
