<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alarm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Alarm API Controller
 * 
 * Handles alarm management for mobile app
 */
class AlarmController extends Controller
{
    /**
     * Get all alarms with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Alarm::with(['device', 'acknowledgedBy']);

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('unacknowledged') && $request->unacknowledged) {
            $query->where('status', 'active');
        }

        $alarms = $query->orderBy('raised_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($alarms);
    }

    /**
     * Get single alarm
     */
    public function show(Alarm $alarm): JsonResponse
    {
        $alarm->load(['device', 'acknowledgedBy']);
        
        return response()->json([
            'data' => $alarm
        ]);
    }

    /**
     * Get alarm statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $deviceId = $request->get('device_id');
        
        $query = Alarm::query();
        
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $stats = [
            'total' => $query->count(),
            'active' => (clone $query)->active()->count(),
            'acknowledged' => (clone $query)->acknowledged()->count(),
            'cleared' => (clone $query)->cleared()->count(),
            'critical' => (clone $query)->where('severity', 'critical')->count(),
            'major' => (clone $query)->where('severity', 'major')->count(),
            'minor' => (clone $query)->where('severity', 'minor')->count(),
            'warning' => (clone $query)->where('severity', 'warning')->count(),
            'info' => (clone $query)->where('severity', 'info')->count(),
            'unacknowledged' => (clone $query)->active()->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Acknowledge alarm
     */
    public function acknowledge(Request $request, Alarm $alarm): JsonResponse
    {
        if ($alarm->status !== 'active') {
            return response()->json([
                'error' => 'Alarm already acknowledged or cleared'
            ], 400);
        }

        $alarm->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'message' => 'Alarm acknowledged successfully',
            'data' => $alarm->fresh(['device', 'acknowledgedBy'])
        ]);
    }

    /**
     * Clear/resolve alarm
     */
    public function clear(Request $request, Alarm $alarm): JsonResponse
    {
        $request->validate([
            'resolution' => 'nullable|string|max:1000',
        ]);

        $alarm->update([
            'status' => 'cleared',
            'cleared_at' => now(),
            'resolution' => $request->resolution,
        ]);

        return response()->json([
            'message' => 'Alarm cleared successfully',
            'data' => $alarm->fresh(['device', 'acknowledgedBy'])
        ]);
    }

    /**
     * Get recent alarms (last 24 hours)
     */
    public function recent(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 24);
        
        $alarms = Alarm::with(['device', 'acknowledgedBy'])
            ->where('raised_at', '>=', now()->subHours($hours))
            ->orderBy('raised_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $alarms,
            'hours' => $hours,
            'count' => $alarms->count()
        ]);
    }
}
