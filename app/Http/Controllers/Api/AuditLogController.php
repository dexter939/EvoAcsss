<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class AuditLogController extends Controller
{
    /**
     * Get audit logs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email');

        // Filter by user
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        // Filter by event
        if ($request->has('event')) {
            $query->event($request->event);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->category($request->category);
        }

        // Filter by severity
        if ($request->has('severity')) {
            $query->severity($request->severity);
        }

        // Filter by date range
        if ($request->has('from') && $request->has('to')) {
            $query->dateRange($request->from, $request->to);
        }

        // Filter by model
        if ($request->has('model_type')) {
            $query->forModel($request->model_type, $request->model_id ?? null);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Only compliance critical
        if ($request->boolean('compliance_critical')) {
            $query->complianceCritical();
        }

        // Order
        $query->orderBy('created_at', $request->get('order', 'desc'));

        // Paginate
        $perPage = min($request->get('per_page', 50), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Get single audit log
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user:id,name,email', 'auditable');

        return response()->json([
            'success' => true,
            'data' => $auditLog,
        ]);
    }

    /**
     * Get audit logs for specific model
     */
    public function forModel(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $logs = AuditLog::forModel($request->model_type, $request->model_id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Export audit logs to CSV
     */
    public function exportCsv(Request $request)
    {
        $query = AuditLog::with('user:id,name,email');

        // Apply same filters as index
        if ($request->has('user_id')) $query->byUser($request->user_id);
        if ($request->has('event')) $query->event($request->event);
        if ($request->has('category')) $query->category($request->category);
        if ($request->has('severity')) $query->severity($request->severity);
        if ($request->has('from') && $request->has('to')) {
            $query->dateRange($request->from, $request->to);
        }
        if ($request->boolean('compliance_critical')) $query->complianceCritical();

        // Limit to prevent memory issues (max 10k records)
        $logs = $query->orderBy('created_at', 'desc')->limit(10000)->get();

        $csvContent = $this->generateCsv($logs);

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"',
        ]);
    }

    /**
     * Export audit logs to JSON
     */
    public function exportJson(Request $request)
    {
        $query = AuditLog::with('user:id,name,email');

        // Apply filters
        if ($request->has('user_id')) $query->byUser($request->user_id);
        if ($request->has('event')) $query->event($request->event);
        if ($request->has('category')) $query->category($request->category);
        if ($request->has('severity')) $query->severity($request->severity);
        if ($request->has('from') && $request->has('to')) {
            $query->dateRange($request->from, $request->to);
        }
        if ($request->boolean('compliance_critical')) $query->complianceCritical();

        $logs = $query->orderBy('created_at', 'desc')->limit(10000)->get();

        return Response::json([
            'export_date' => now()->toIso8601String(),
            'total_records' => $logs->count(),
            'filters' => $request->only(['user_id', 'event', 'category', 'severity', 'from', 'to']),
            'logs' => $logs,
        ])->header('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d_His') . '.json"');
    }

    /**
     * Get audit statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to = $request->get('to', now()->toDateString());

        $stats = [
            'total_logs' => AuditLog::dateRange($from, $to)->count(),
            'by_event' => AuditLog::dateRange($from, $to)
                ->selectRaw('event, count(*) as count')
                ->groupBy('event')
                ->pluck('count', 'event'),
            'by_category' => AuditLog::dateRange($from, $to)
                ->selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category'),
            'by_severity' => AuditLog::dateRange($from, $to)
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),
            'by_user' => AuditLog::dateRange($from, $to)
                ->whereNotNull('user_id')
                ->with('user:id,name,email')
                ->selectRaw('user_id, count(*) as count')
                ->groupBy('user_id')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'user' => $item->user?->name ?? 'Unknown',
                        'count' => $item->count,
                    ];
                }),
            'compliance_critical' => AuditLog::dateRange($from, $to)
                ->complianceCritical()
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Generate CSV content from logs
     */
    private function generateCsv($logs): string
    {
        $csv = "ID,Date,User,Event,Category,Severity,Action,Description,IP Address,URL\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->id,
                $log->created_at->toDateTimeString(),
                $log->user?->email ?? $log->user_email ?? 'System',
                $log->event,
                $log->category,
                $log->severity,
                str_replace(['"', ','], '', $log->action ?? ''),
                str_replace(['"', ','], '', $log->description ?? ''),
                $log->ip_address ?? '',
                $log->url ?? ''
            );
        }

        return $csv;
    }
}
