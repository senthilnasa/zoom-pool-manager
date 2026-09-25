<?php

namespace App\Domain\Installer\Services;

use App\Domain\Auth\Models\MfaRecoveryCode;
use App\Domain\Auth\Models\MfaSecret;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Http\Middleware\EnsureInstalled;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InstallerService
{
    /**
     * Required PHP extensions.
     *
     * @var array<string, string>
     */
    protected array $requiredExtensions = [
        'pdo_mysql' => 'PDO MySQL Driver',
        'bcmath' => 'BCMath Arbitrary Precision Mathematics',
        'ctype' => 'Character type checking',
        'curl' => 'Client URL Library',
        'dom' => 'DOM XML Parser',
        'fileinfo' => 'File Information',
        'filter' => 'Filter extension',
        'json' => 'JSON Parser & Encoder',
        'mbstring' => 'Multibyte String',
        'openssl' => 'OpenSSL Cryptography',
        'pcre' => 'PCRE Regular Expressions',
        'session' => 'Session Handling',
        'tokenizer' => 'Tokenizer',
        'xml' => 'XML Parser',
        'zip' => 'Zip Archive handling',
        'gmp' => 'GMP Arbitrary Precision Mathematics',
        'intl' => 'Internationalization extension',
    ];

    /**
     * Writable paths.
     *
     * @var array<int, string>
     */
    protected array $writablePaths = [
        'storage',
        'storage/app',
        'storage/framework',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    /**
     * Check system requirements.
     *
     * @return array<string, mixed>
     */
    public function checkRequirements(): array
    {
        $phpVersion = PHP_VERSION;
        $phpSatisfied = version_compare($phpVersion, '8.3.0', '>=');

        $extensions = [];
        $extensionsSatisfied = true;
        foreach ($this->requiredExtensions as $ext => $name) {
            $loaded = extension_loaded($ext);
            $extensions[$ext] = [
                'name' => $name,
                'loaded' => $loaded,
            ];
            if (! $loaded) {
                $extensionsSatisfied = false;
            }
        }

        $permissions = [];
        $permissionsSatisfied = true;
        foreach ($this->writablePaths as $path) {
            $fullPath = base_path($path);
            if (! is_dir($fullPath)) {
                @mkdir($fullPath, 0775, true);
            }
            $isWritable = is_dir($fullPath) && is_writable($fullPath);
            $permissions[$path] = [
                'path' => $path,
                'writable' => $isWritable,
            ];
            if (! $isWritable) {
                $permissionsSatisfied = false;
            }
        }

        return [
            'php' => [
                'version' => $phpVersion,
                'required' => '8.3.0',
                'satisfied' => $phpSatisfied,
            ],
            'extensions' => $extensions,
            'extensionsSatisfied' => $extensionsSatisfied,
            'permissions' => $permissions,
            'permissionsSatisfied' => $permissionsSatisfied,
            'allSatisfied' => $phpSatisfied && $extensionsSatisfied && $permissionsSatisfied,
        ];
    }

    /**
     * Test database connection.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function testDatabaseConnection(array $config): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? 'zpm'
            );

            $pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            $isMariaDb = str_contains(strtolower((string) $version), 'mariadb');

            return [
                'success' => true,
                'version' => $version,
                'type' => $isMariaDb ? 'MariaDB' : 'MySQL',
                'message' => 'Connection established successfully.',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update environment file with database credentials.
     *
     * @param  array<string, mixed>  $config
     */
    public function saveDatabaseConfig(array $config): void
    {
        $envPath = function_exists('app') && app()->has('path') ? app()->environmentFilePath() : base_path('.env');
        if (! file_exists($envPath)) {
            $examplePath = base_path('.env.example');
            if (file_exists($examplePath)) {
                copy($examplePath, $envPath);
            }
        }

        $content = file_get_contents($envPath);

        $replacements = [
            'DB_HOST' => $config['host'],
            'DB_PORT' => $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
        ];

        foreach ($replacements as $key => $val) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}=\"{$val}\"", $content);
        }

        file_put_contents($envPath, $content);
    }

    /**
     * Execute database migrations.
     *
     * @return array<string, mixed>
     */
    public function runMigrations(): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            $this->seedInitialRolesAndPermissions();

            return [
                'success' => true,
                'output' => $output,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Seed Spatie roles and permissions based on SPEC Part H3.
     */
    public function seedInitialRolesAndPermissions(): void
    {
        $roles = [
            'super_admin' => 'Super Administrator',
            'it_admin' => 'IT Administrator',
            'meeting_admin' => 'Meeting Administrator',
            'approver' => 'Approver',
            'dept_admin' => 'Department Administrator',
            'department_admin' => 'Department Administrator',
            'faculty' => 'Faculty',
            'staff' => 'Staff',
            'auditor' => 'Viewer / Auditor',
            'api_client' => 'API Client',
        ];

        foreach ($roles as $name => $label) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $permissions = [
            'meeting.create', 'meeting.view', 'meeting.view_any', 'meeting.edit',
            'meeting.cancel', 'meeting.approve', 'meeting.allocate', 'meeting.override',
            'meeting.reschedule', 'meeting.book_on_behalf', 'meeting.start_as_host',
            'recording.view', 'recording.view_any', 'recording.manage', 'recording.download',
            'recording.share', 'zoom.view', 'zoom.manage', 'resource.view', 'resource.manage',
            'pool.manage', 'template.manage', 'security_profile.manage', 'workflow.view',
            'workflow.manage', 'quota.manage', 'user.view', 'user.manage', 'settings.view',
            'settings.manage', 'audit.view', 'backup.manage', 'api.manage', 'health.view',
            'emergency.use', 'privacy.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Super Admin gets all permissions
        $superAdmin = Role::findByName('super_admin', 'web');
        $superAdmin->syncPermissions(Permission::all());
    }

    /**
     * Generate TOTP secret, QR code, and single-use recovery codes.
     *
     * @return array<string, mixed>
     */
    public function generateMfaSetup(string $email): array
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        $orgName = config('app.organization_name', 'Zoom Pool Manager');
        $otpUrl = $google2fa->getQRCodeUrl($orgName, $email, $secret);

        // Render QR Code as SVG
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svgString = $writer->writeString($otpUrl);
        $qrBase64 = 'data:image/svg+xml;base64,'.base64_encode($svgString);

        // Generate 10 random 10-character alphanumeric recovery codes
        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return [
            'secret' => $secret,
            'qr_code' => $qrBase64,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Verify a submitted TOTP code against a secret.
     */
    public function verifyTotp(string $secret, string $code): bool
    {
        $google2fa = new Google2FA;

        return (bool) $google2fa->verifyKey($secret, $code, 2); // 2 window periods allowed for slight clock skew
    }

    /**
     * Create the first local Super Administrator.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSuperAdmin(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'locale' => 'en',
                'is_active' => true,
                'mfa_enabled' => true,
            ]);

            $user->assignRole('super_admin');

            // Store encrypted TOTP secret
            MfaSecret::create([
                'user_id' => $user->id,
                'secret' => $data['mfa_secret'],
                'enrolled_at' => now(),
            ]);

            // Store hashed recovery codes
            foreach ($data['recovery_codes'] as $code) {
                MfaRecoveryCode::create([
                    'user_id' => $user->id,
                    'code_hash' => hash('sha256', strtoupper(trim($code))),
                ]);
            }

            return $user;
        });
    }

    /**
     * Save Organization settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOrganizationSettings(array $data): void
    {
        Setting::set('organization_name', $data['organization_name'] ?? 'Zoom Pool Manager');
        Setting::set('organization_timezone', $data['timezone'] ?? 'Asia/Kolkata');
        Setting::set('default_buffer_minutes', (int) ($data['buffer_minutes'] ?? 10));
    }

    /**
     * Complete installation and write lock file.
     */
    public function completeInstallation(): void
    {
        $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
        file_put_contents($lockFile, json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => '1.0.0-dev',
        ], JSON_PRETTY_PRINT));

        Setting::set('installed_at', now()->toIso8601String());
        Setting::set('app_version', '1.0.0-dev');

        // Regenerate app key if missing
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }
}
