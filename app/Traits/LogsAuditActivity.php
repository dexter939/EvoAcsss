<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogsAuditActivity
{
    protected function logAuditActivity(
        string $action,
        string $entityType,
        int|string $entityId,
        string $description,
        array $metadata = []
    ): void {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'tenant_id' => Auth::user()?->tenant_id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
