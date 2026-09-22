<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\Quota;
use App\Domain\Workflow\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class QuotaController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService,
        protected AuditService $auditService
    ) {}

    /**
     * Display listing of configured quotas and their monthly consumption.
     */
    public function index(): View
    {
        $quotas = Quota::with(['usages'])->paginate(20);
        $year = (int) Carbon::now()->format('Y');
        $month = (int) Carbon::now()->format('n');

        $quotasWithStats = $quotas->through(function (Quota $quota) use ($year, $month) {
            $stats = $this->quotaService->getUsageStats($quota, $year, $month);
            $targetName = 'Unknown';

            if ($quota->scope_type === 'user') {
                $u = User::find($quota->scope_id);
                $targetName = $u ? "User: {$u->name}" : "User #{$quota->scope_id}";
            } elseif ($quota->scope_type === 'department') {
                $d = Department::find($quota->scope_id);
                $targetName = $d ? "Dept: {$d->name}" : "Dept #{$quota->scope_id}";
            }

            return [
                'model' => $quota,
                'target_name' => $targetName,
                'stats' => $stats,
            ];
        });

        return view('quotas.index', [
            'quotas' => $quotasWithStats,
            'departments' => Department::all(),
            'users' => User::orderBy('name')->take(50)->get(),
            'currentPeriod' => Carbon::now()->format('F Y'),
        ]);
    }

    /**
     * Store or update quota.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope_type' => ['required', 'string', 'in:user,department'],
            'scope_id' => ['required', 'integer'],
            'max_meetings_per_month' => ['nullable', 'integer', 'min:1'],
            'max_hours_per_month' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        /** @var Quota $quota */
        $quota = Quota::updateOrCreate(
            [
                'scope_type' => $validated['scope_type'],
                'scope_id' => $validated['scope_id'],
            ],
            [
                'max_meetings_per_month' => $validated['max_meetings_per_month'] ?? null,
                'max_hours_per_month' => $validated['max_hours_per_month'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        $this->auditService->log(
            event: 'quota.saved',
            auditable: $quota,
            actor: $request->user(),
            newValues: $validated
        );

        return redirect()->route('quotas.index')
            ->with('status', 'Quota successfully saved.');
    }

    /**
     * Delete quota.
     */
    public function destroy(Request $request, string $publicId): RedirectResponse
    {
        /** @var Quota $quota */
        $quota = Quota::where('public_id', $publicId)->firstOrFail();

        $this->auditService->log(
            event: 'quota.deleted',
            auditable: $quota,
            actor: $request->user(),
            oldValues: ['scope_type' => $quota->scope_type, 'scope_id' => $quota->scope_id]
        );

        $quota->delete();

        return redirect()->route('quotas.index')
            ->with('status', 'Quota deleted.');
    }
}
