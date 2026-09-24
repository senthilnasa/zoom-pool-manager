<?php

namespace App\Http\Controllers\Api;

use App\Domain\Search\Services\GlobalSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $searchService
    ) {}

    /**
     * Execute a global application search across modules, users, meetings, and pools.
     */
    public function search(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $results = $this->searchService->search($q);

        return response()->json([
            'query' => $q,
            'results' => $results,
            'total_matches' => count($results['modules']) + count($results['users']) + count($results['meetings']) + count($results['pools']),
        ]);
    }
}
