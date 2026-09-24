<?php

namespace App\Http\Controllers\Api;

use App\Domain\Zoom\Services\ZoomAccountUsageReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpaReportController extends Controller
{
    public function __construct(
        protected ZoomAccountUsageReportService $reportService
    ) {}

    /**
     * Get Zoom account usage and concurrency report.
     */
    public function zoomAccountUsage(Request $request): JsonResponse
    {
        /** @var array{start_date?: ?string, end_date?: ?string, pool_id?: int|string|null, search?: ?string} $filters */
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'pool_id' => 'nullable',
            'search' => 'nullable|string|max:100',
        ]);

        $data = $this->reportService->generateReport($filters);

        return response()->json($data);
    }

    /**
     * Export Zoom account usage and concurrency report to CSV.
     */
    public function exportZoomAccountUsage(Request $request): StreamedResponse
    {
        /** @var array{start_date?: ?string, end_date?: ?string, pool_id?: int|string|null, search?: ?string} $filters */
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'pool_id' => 'nullable',
            'search' => 'nullable|string|max:100',
        ]);

        return $this->reportService->exportCsv($filters);
    }
}
