<?php

namespace App\Http\Controllers;

use App\Services\SystemUpdateService;
use App\Services\GitHub\GitHubReleaseService;
use App\Services\GitHub\UpdateStagingService;
use App\Services\GitHub\UpdateApplicationService;
use App\Models\SystemVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemUpdateController extends Controller
{
    private SystemUpdateService $updateService;
    private GitHubReleaseService $githubService;
    private UpdateStagingService $stagingService;
    private UpdateApplicationService $applicationService;

    public function __construct(
        SystemUpdateService $updateService,
        GitHubReleaseService $githubService,
        UpdateStagingService $stagingService,
        UpdateApplicationService $applicationService
    ) {
        $this->updateService = $updateService;
        $this->githubService = $githubService;
        $this->stagingService = $stagingService;
        $this->applicationService = $applicationService;
    }

    public function dashboard()
    {
        $status = $this->updateService->getSystemStatus();
        $history = SystemVersion::latest('deployed_at')
            ->limit(20)
            ->get();

        $stats = [
            'total_deployments' => SystemVersion::count(),
            'successful_deployments' => SystemVersion::successful()->count(),
            'failed_deployments' => SystemVersion::failed()->count(),
            'last_24h_deployments' => SystemVersion::where('deployed_at', '>=', now()->subDay())->count(),
        ];

        return view('acs.system-updates', compact('status', 'history', 'stats'));
    }

    public function status(Request $request)
    {
        $environment = $request->query('environment', config('app.env'));
        return response()->json($this->updateService->getSystemStatus($environment));
    }

    public function history(Request $request)
    {
        $limit = $request->query('limit', 10);
        $history = $this->updateService->getUpdateHistory($limit);

        return response()->json([
            'status' => 'success',
            'history' => $history,
        ]);
    }

    public function runUpdate(Request $request)
    {
        $request->validate([
            'force' => 'sometimes|boolean',
            'environment' => 'sometimes|in:development,staging,production',
        ]);

        $result = $this->updateService->performAutoUpdate(
            $request->input('environment', config('app.env'))
        );

        return response()->json($result);
    }

    public function healthCheck(Request $request)
    {
        $environment = $request->query('environment', config('app.env'));
        $current = SystemVersion::getCurrentVersion($environment);

        if (!$current) {
            return response()->json([
                'status' => 'warning',
                'message' => 'No deployment records found',
                'healthy' => false,
            ], 200);
        }

        $healthChecks = $current->health_check_results ?? [];

        return response()->json([
            'status' => $current->is_healthy ? 'healthy' : 'degraded',
            'version' => $current->version,
            'deployed_at' => $current->deployed_at?->toIso8601String(),
            'health_checks' => $healthChecks,
            'healthy' => $current->is_healthy,
        ]);
    }

    public function versionInfo(Request $request)
    {
        $environment = $request->query('environment', config('app.env'));
        $current = SystemVersion::getCurrentVersion($environment);

        return response()->json([
            'current_version' => $current?->version ?? 'unknown',
            'deployment_status' => $current?->deployment_status ?? 'unknown',
            'deployed_at' => $current?->deployed_at?->toIso8601String(),
            'environment' => config('app.env'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
        ]);
    }

    public function pendingUpdates(Request $request)
    {
        $environment = $request->query('environment', config('app.env'));
        
        $pending = SystemVersion::pendingApproval()
            ->forEnvironment($environment)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'updates' => $pending->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'release_tag' => $version->github_release_tag,
                    'release_url' => $version->github_release_url,
                    'changelog' => $version->changelog,
                    'release_notes' => $version->release_notes,
                    'created_at' => $version->created_at->toIso8601String(),
                    'approval_status' => $version->approval_status,
                ];
            }),
            'count' => $pending->count(),
        ]);
    }

    public function approveUpdate(Request $request, int $id)
    {
        $version = SystemVersion::find($id);

        if (!$version) {
            return response()->json([
                'success' => false,
                'error' => 'Update not found',
            ], 404);
        }

        if ($version->approval_status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Update is not pending approval',
                'current_status' => $version->approval_status,
            ], 400);
        }

        $user = $request->user();

        $version->approve($user?->id);

        return response()->json([
            'success' => true,
            'message' => 'Update approved successfully',
            'version' => $version->version,
            'approved_by' => $user?->name ?? 'admin',
            'approved_at' => $version->approved_at->toIso8601String(),
        ]);
    }

    public function rejectUpdate(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $version = SystemVersion::find($id);

        if (!$version) {
            return response()->json([
                'success' => false,
                'error' => 'Update not found',
            ], 404);
        }

        if ($version->approval_status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Update is not pending approval',
                'current_status' => $version->approval_status,
            ], 400);
        }

        $user = $request->user();
        $reason = $request->input('reason');

        $version->reject($user?->id, $reason);

        $this->stagingService->cleanupStagedUpdate($version);

        return response()->json([
            'success' => true,
            'message' => 'Update rejected and cleaned up',
            'version' => $version->version,
            'rejected_by' => $user?->name ?? 'admin',
        ]);
    }

    public function scheduleUpdate(Request $request, int $id)
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $version = SystemVersion::find($id);

        if (!$version) {
            return response()->json([
                'success' => false,
                'error' => 'Update not found',
            ], 404);
        }

        if ($version->approval_status !== 'approved') {
            return response()->json([
                'success' => false,
                'error' => 'Update must be approved before scheduling',
                'current_status' => $version->approval_status,
            ], 400);
        }

        $scheduledAt = new \DateTime($request->input('scheduled_at'));
        $version->scheduleDeployment($scheduledAt);

        return response()->json([
            'success' => true,
            'message' => 'Update scheduled successfully',
            'version' => $version->version,
            'scheduled_at' => $version->scheduled_at->toIso8601String(),
        ]);
    }

    public function applyUpdate(Request $request, int $id)
    {
        $version = SystemVersion::find($id);

        if (!$version) {
            return response()->json([
                'success' => false,
                'error' => 'Update not found',
            ], 404);
        }

        if ($version->approval_status !== 'approved') {
            return response()->json([
                'success' => false,
                'error' => 'Update must be approved before applying',
                'current_status' => $version->approval_status,
            ], 400);
        }

        $result = $this->applicationService->applyUpdate($version);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function validateStagedUpdate(Request $request, int $id)
    {
        $version = SystemVersion::find($id);

        if (!$version) {
            return response()->json([
                'success' => false,
                'error' => 'Update not found',
            ], 404);
        }

        $validation = $this->stagingService->validateStagedUpdate($version);

        return response()->json([
            'success' => true,
            'version' => $version->version,
            'checks' => $validation,
        ]);
    }
}
