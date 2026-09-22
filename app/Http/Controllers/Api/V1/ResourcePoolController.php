<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourcePoolController extends Controller
{
    /**
     * List resource pools.
     */
    public function index(Request $request): JsonResponse
    {
        $pools = ResourcePool::where('is_active', true)
            ->with(['resources'])
            ->get();

        $data = [];
        foreach ($pools as $pool) {
            $data[] = [
                'public_id' => $pool->public_id,
                'name' => $pool->name,
                'description' => $pool->description,
                'strategy' => $pool->pool_strategy,
                'resources_count' => $pool->resources->count(),
                'resources' => $pool->resources->map(function (ZoomResource $resource) {
                    return [
                        'public_id' => $resource->public_id,
                        'name' => $resource->name,
                        'capacity' => $resource->participant_capacity,
                        'managed' => $resource->managed,
                    ];
                }),
            ];
        }

        return response()->json([
            'pools' => $data,
        ]);
    }
}
