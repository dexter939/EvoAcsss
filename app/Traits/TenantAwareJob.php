<?php

namespace App\Traits;

use App\Contexts\TenantContext;
use App\Models\Tenant;

trait TenantAwareJob
{
    public ?int $tenantId = null;

    public function setTenantFromContext(): self
    {
        if (TenantContext::check()) {
            $this->tenantId = TenantContext::id();
        }
        return $this;
    }

    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function tags(): array
    {
        $tags = [];

        $resolvedTenantId = $this->getTenantIdForTags();
        if ($resolvedTenantId) {
            $tags[] = "tenant:{$resolvedTenantId}";
        }

        $tags[] = 'job:' . class_basename(static::class);

        if (property_exists($this, 'task') && $this->task) {
            $tags[] = "task:{$this->task->id}";
            if ($this->task->cpeDevice) {
                $tags[] = "device:{$this->task->cpeDevice->id}";
            }
        }

        if (property_exists($this, 'deployment') && $this->deployment) {
            $tags[] = "deployment:{$this->deployment->id}";
            if ($this->deployment->cpeDevice) {
                $tags[] = "device:{$this->deployment->cpeDevice->id}";
            }
        }

        if (property_exists($this, 'deviceId') && $this->deviceId) {
            $tags[] = "device:{$this->deviceId}";
        }

        if (property_exists($this, 'device') && $this->device) {
            $tags[] = "device:{$this->device->id}";
        }

        if (property_exists($this, 'alert') && $this->alert) {
            $tags[] = "alert:{$this->alert->id}";
        }

        if (property_exists($this, 'storageService') && $this->storageService) {
            $tags[] = "storage:{$this->storageService->id}";
            if ($this->storageService->cpeDevice) {
                $tags[] = "device:{$this->storageService->cpeDevice->id}";
            }
        }

        if (property_exists($this, 'voiceService') && $this->voiceService) {
            $tags[] = "voice:{$this->voiceService->id}";
            if ($this->voiceService->cpeDevice) {
                $tags[] = "device:{$this->voiceService->cpeDevice->id}";
            }
        }

        return array_unique($tags);
    }

    protected function getTenantIdForTags(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        if (property_exists($this, 'task') && $this->task?->cpeDevice?->tenant_id) {
            return $this->task->cpeDevice->tenant_id;
        }

        if (property_exists($this, 'deployment') && $this->deployment?->cpeDevice?->tenant_id) {
            return $this->deployment->cpeDevice->tenant_id;
        }

        if (property_exists($this, 'device') && $this->device?->tenant_id) {
            return $this->device->tenant_id;
        }

        if (property_exists($this, 'alarm') && $this->alarm?->tenant_id) {
            return $this->alarm->tenant_id;
        }

        if (property_exists($this, 'storageService') && $this->storageService?->cpeDevice?->tenant_id) {
            return $this->storageService->cpeDevice->tenant_id;
        }

        if (property_exists($this, 'voiceService') && $this->voiceService?->cpeDevice?->tenant_id) {
            return $this->voiceService->cpeDevice->tenant_id;
        }

        return null;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId ?? $this->getTenantIdForTags();
    }

    public function middleware(): array
    {
        return [
            new \App\Jobs\Middleware\RestoreTenantContext(),
        ];
    }
}
