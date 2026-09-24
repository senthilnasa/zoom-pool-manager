<?php

namespace App\Domain\Search\Services;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;

class GlobalSearchService
{
    /**
     * Complete index of navigable application modules and features with keywords.
     *
     * @return array<int, array{title: string, category: string, path: string, description: string, keywords: array<string>}>
     */
    public function getModulesCatalog(): array
    {
        return [
            // Core Scheduling
            [
                'title' => 'Dashboard Overview',
                'category' => 'Core Scheduling',
                'path' => '/app/dashboard',
                'description' => 'Real-time Zoom pooled metrics, upcoming sessions, and capacity gauges.',
                'keywords' => ['dashboard', 'home', 'overview', 'metrics', 'stats', 'analytics'],
            ],
            [
                'title' => 'Scheduled Meetings',
                'category' => 'Core Scheduling',
                'path' => '/app/meetings',
                'description' => 'Manage, filter, and inspect all institutional Zoom meetings and host keys.',
                'keywords' => ['meetings', 'all meetings', 'scheduled', 'sessions', 'host key', 'start host'],
            ],
            [
                'title' => 'Book a Meeting',
                'category' => 'Core Scheduling',
                'path' => '/app/meetings/create',
                'description' => 'Reserve a pooled Zoom license with conflict detection and automated allocation.',
                'keywords' => ['book meeting', 'schedule', 'new meeting', 'reserve', 'create meeting', 'book on behalf'],
            ],
            [
                'title' => 'Resource Calendar',
                'category' => 'Core Scheduling',
                'path' => '/app/calendar',
                'description' => 'Interactive visual calendar view of pool reservations across rooms and days.',
                'keywords' => ['calendar', 'timetable', 'schedule view', 'timeline', 'resource calendar'],
            ],
            [
                'title' => 'Recurring Series',
                'category' => 'Core Scheduling',
                'path' => '/app/series',
                'description' => 'Multi-occurrence semester courses, weekly lectures, and series detachments.',
                'keywords' => ['series', 'recurring', 'courses', 'weekly', 'repeat', 'occurrences'],
            ],

            // Identity & Access Control
            [
                'title' => 'User Directory & Access Control',
                'category' => 'Identity & Governance',
                'path' => '/app/users',
                'description' => 'Manage institutional accounts, assign system roles, and check user permissions.',
                'keywords' => ['users', 'user management', 'directory', 'accounts', 'faculty', 'staff', 'members', 'people'],
            ],
            [
                'title' => 'Role Management & Permissions',
                'category' => 'Identity & Governance',
                'path' => '/app/users?tab=roles',
                'description' => 'Configure core roles (Super Admin, Approval, User), create custom roles, and set permissions.',
                'keywords' => ['roles', 'role management', 'permissions', 'access control', 'custom roles', 'rbac', 'privileges'],
            ],
            [
                'title' => 'Department Directory',
                'category' => 'Identity & Governance',
                'path' => '/app/departments',
                'description' => 'Manage academic and administrative divisions and department member counts.',
                'keywords' => ['departments', 'divisions', 'faculties', 'schools', 'units'],
            ],
            [
                'title' => 'Approvals Queue',
                'category' => 'Identity & Governance',
                'path' => '/app/approvals',
                'description' => 'Review and decide on booking requests requiring institutional clearance.',
                'keywords' => ['approvals', 'approval queue', 'requests', 'pending', 'decide', 'signoff'],
            ],
            [
                'title' => 'Workflow Rules Builder',
                'category' => 'Identity & Governance',
                'path' => '/app/workflows',
                'description' => 'Configure conditional rules for automatic approval, escalation, or rejection.',
                'keywords' => ['workflows', 'rules', 'automation', 'conditions', 'escalation'],
            ],
            [
                'title' => 'Department Quotas',
                'category' => 'Identity & Governance',
                'path' => '/app/quotas',
                'description' => 'Monitor and limit monthly pooled hours and meeting limits per department.',
                'keywords' => ['quotas', 'limits', 'allocation limits', 'monthly hours'],
            ],
            [
                'title' => 'Delegations',
                'category' => 'Identity & Governance',
                'path' => '/app/delegations',
                'description' => 'Configure approval delegation windows for out-of-office approvers.',
                'keywords' => ['delegations', 'out of office', 'substitute approver'],
            ],
            [
                'title' => 'Waiting Queue',
                'category' => 'Identity & Governance',
                'path' => '/app/waitlist',
                'description' => 'Automated waitlist queue prioritizing standby meeting requests on pool saturation.',
                'keywords' => ['waitlist', 'queue', 'standby', 'waitlist queue'],
            ],

            // Host Pools & Media
            [
                'title' => 'Zoom Resource Pools',
                'category' => 'Pools & Resources',
                'path' => '/app/pools',
                'description' => 'Manage pooled Zoom host accounts, capacity levels, and prioritization strategies.',
                'keywords' => ['pools', 'zoom pools', 'licenses', 'resource pools', 'host accounts'],
            ],
            [
                'title' => 'Cloud Recordings',
                'category' => 'Media & Analytics',
                'path' => '/app/recordings',
                'description' => 'Synchronize, search, play back, and download Zoom cloud recordings.',
                'keywords' => ['recordings', 'cloud recordings', 'videos', 'mp4', 'transcripts', 'media'],
            ],
            [
                'title' => 'Meeting Attendance',
                'category' => 'Media & Analytics',
                'path' => '/app/attendance',
                'description' => 'Participant join/leave logs, duration tracking, and CSV export for completed meetings.',
                'keywords' => ['attendance', 'participants', 'join times', 'reports', 'attendance logs'],
            ],
            [
                'title' => 'State Drift Reconciliation',
                'category' => 'Media & Analytics',
                'path' => '/app/drift',
                'description' => 'Detect out-of-band external changes in Zoom and reconcile with local pool state.',
                'keywords' => ['drift', 'reconcile', 'conflicts', 'sync drift'],
            ],

            // System & Integrations
            [
                'title' => 'Zoom Server-to-Server OAuth',
                'category' => 'Settings & Integrations',
                'path' => '/app/settings/zoom',
                'description' => 'Configure Zoom Marketplace Server-to-Server OAuth credentials and test connection.',
                'keywords' => ['zoom config', 'zoom oauth', 'credentials', 'account id', 'client id', 'client secret'],
            ],
            [
                'title' => 'SSO & SAML Login Configuration',
                'category' => 'Settings & Integrations',
                'path' => '/app/settings/sso',
                'description' => 'Configure Google Workspace OAuth, Microsoft Entra ID, and SAML 2.0 Identity Providers.',
                'keywords' => ['sso', 'saml', 'login', 'google login', 'microsoft login', 'entra id', 'single sign on', 'sp metadata'],
            ],
            [
                'title' => 'Directory & Active Directory (AD) Sync',
                'category' => 'Settings & Integrations',
                'path' => '/app/settings/directory-sync',
                'description' => 'Automate user and department provisioning from Microsoft Entra, Google Workspace, or LDAP.',
                'keywords' => ['directory sync', 'ad sync', 'active directory', 'ldap', 'google directory', 'entra sync', 'user sync'],
            ],
            [
                'title' => 'Scheduled Jobs & Automation Cadence',
                'category' => 'Settings & Integrations',
                'path' => '/app/settings/jobs',
                'description' => 'Configure background cron intervals, monitor scheduler heartbeats, and run jobs on demand.',
                'keywords' => ['scheduled jobs', 'cron', 'cron jobs', 'background jobs', 'heartbeat', 'daemon', 'cadence'],
            ],
            [
                'title' => 'Mail Server Configuration',
                'category' => 'Settings & Integrations',
                'path' => '/app/mail/settings',
                'description' => 'Configure SMTP, Microsoft Graph, or Gmail API mail drivers and test delivery.',
                'keywords' => ['mail', 'smtp', 'email', 'mailer', 'gmail', 'graph mail'],
            ],
            [
                'title' => 'Email Templates',
                'category' => 'Settings & Integrations',
                'path' => '/app/mail/templates',
                'description' => 'Customize notification templates for meeting confirmations, host keys, and reminders.',
                'keywords' => ['email templates', 'templates', 'notifications email', 'custom emails'],
            ],
            [
                'title' => 'API Keys & Outbound Webhooks',
                'category' => 'Developer & API',
                'path' => '/app/api/keys',
                'description' => 'Generate bearer tokens with granular scopes for institutional SIS/ERP integrations.',
                'keywords' => ['api', 'api keys', 'tokens', 'bearer', 'rest api', 'integration'],
            ],
            [
                'title' => 'System Updates & Releases',
                'category' => 'Settings & Integrations',
                'path' => '/app/settings/updates',
                'description' => 'Check for official upstream updates, review release changelogs, and safely upgrade.',
                'keywords' => ['updates', 'system updates', 'version', 'release', 'upgrade'],
            ],
            [
                'title' => 'Cryptographic Audit Trail',
                'category' => 'Operations & Security',
                'path' => '/app/audit',
                'description' => 'Tamper-evident SHA-256 chained audit logs tracking all administrator and system actions.',
                'keywords' => ['audit', 'audit logs', 'trail', 'security log', 'compliance', 'tamper evident'],
            ],
            [
                'title' => 'System Backups',
                'category' => 'Operations & Security',
                'path' => '/app/operations/backups',
                'description' => 'Create encrypted database dumps, download snapshots, and inspect backup integrity.',
                'keywords' => ['backups', 'database dump', 'snapshot', 'restore'],
            ],
            [
                'title' => 'Operations Health Telemetry',
                'category' => 'Operations & Security',
                'path' => '/app/operations/health',
                'description' => 'Inspect database latency, queue backlogs, disk storage, and run automated diagnostics.',
                'keywords' => ['health', 'telemetry', 'diagnostics', 'system health', 'status'],
            ],
        ];
    }

    /**
     * Search globally across modules, users, meetings, and pools.
     *
     * @return array{modules: array<int, mixed>, users: array<int, mixed>, meetings: array<int, mixed>, pools: array<int, mixed>}
     */
    public function search(string $query): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [
                'modules' => [],
                'users' => [],
                'meetings' => [],
                'pools' => [],
            ];
        }

        $term = mb_strtolower($q);

        // 1. Search Modules Catalog
        $matchedModules = [];
        $catalog = $this->getModulesCatalog();
        foreach ($catalog as $item) {
            $titleMatch = str_contains(mb_strtolower($item['title']), $term);
            $descMatch = str_contains(mb_strtolower($item['description']), $term);
            $keywordMatch = false;
            foreach ($item['keywords'] as $kw) {
                if (str_contains(mb_strtolower($kw), $term) || str_contains($term, mb_strtolower($kw))) {
                    $keywordMatch = true;
                    break;
                }
            }

            if ($titleMatch || $descMatch || $keywordMatch) {
                $matchedModules[] = [
                    'title' => $item['title'],
                    'category' => $item['category'],
                    'path' => $item['path'],
                    'description' => $item['description'],
                ];
            }
        }

        // 2. Search Users
        $users = User::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->with(['department', 'roles'])
            ->take(6)
            ->get();

        $matchedUsers = [];
        foreach ($users as $u) {
            $userKey = $u->public_id ?: $u->id;
            $matchedUsers[] = [
                'id' => $u->id,
                'public_id' => $u->public_id,
                'name' => $u->name,
                'email' => $u->email,
                'department' => $u->department ? $u->department->name : 'No Department',
                'role' => $u->getRoleNames()->first() ?? 'User',
                'path' => '/app/users/'.$userKey.'/profile',
                'profile_path' => '/app/users/'.$userKey.'/profile',
                'directory_path' => '/app/users?search='.urlencode($u->email),
            ];
        }

        // 3. Search Meetings
        $meetings = Meeting::query()
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('public_id', 'like', "%{$q}%");
            })
            ->take(5)
            ->get();

        $matchedMeetings = [];
        foreach ($meetings as $m) {
            $matchedMeetings[] = [
                'id' => $m->id,
                'public_id' => $m->public_id,
                'title' => $m->title,
                'status' => $m->status,
                'start_time' => $m->starts_at?->toIso8601String(),
                'path' => '/app/meetings?search='.urlencode($m->title),
            ];
        }

        // 4. Search Resource Pools
        $pools = ResourcePool::query()
            ->where('name', 'like', "%{$q}%")
            ->take(4)
            ->get();

        $matchedPools = [];
        foreach ($pools as $p) {
            $matchedPools[] = [
                'id' => $p->id,
                'name' => $p->name,
                'path' => '/app/pools',
            ];
        }

        return [
            'modules' => array_slice($matchedModules, 0, 8),
            'users' => $matchedUsers,
            'meetings' => $matchedMeetings,
            'pools' => $matchedPools,
        ];
    }
}
