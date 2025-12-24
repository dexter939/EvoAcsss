<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = $this->loadSettings();
        
        return view('acs.settings', [
            'settings' => $settings,
            'serverInfo' => $this->getServerInfo(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'acs_url' => 'nullable|string|max:500',
            'acs_username' => 'nullable|string|max:255',
            'acs_password' => 'nullable|string|max:255',
            'inform_interval' => 'nullable|integer|min:60|max:86400',
            'connection_request_username' => 'nullable|string|max:255',
            'connection_request_password' => 'nullable|string|max:255',
            'tr069_ssl_enabled' => 'nullable',
            'tr069_auth_method' => 'nullable|in:none,basic,digest',
            'session_timeout' => 'nullable|integer|min:300|max:86400',
            'max_devices' => 'nullable|integer|min:100|max:1000000',
            'log_level' => 'nullable|in:debug,info,warning,error',
            'enable_debug' => 'nullable',
        ]);

        $validated['tr069_ssl_enabled'] = $request->has('tr069_ssl_enabled');
        $validated['enable_debug'] = $request->has('enable_debug');

        $this->saveSettings($validated);
        
        Cache::flush();

        return redirect()->route('acs.settings')
            ->with('success', 'Impostazioni salvate con successo.');
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return redirect()->route('acs.settings')
            ->with('success', 'Cache svuotata con successo.');
    }

    protected function loadSettings(): array
    {
        if (!Schema::hasTable('settings')) {
            return $this->getDefaultSettings();
        }

        try {
            $dbSettings = Setting::getAll();
            return array_merge($this->getDefaultSettings(), $dbSettings);
        } catch (\Exception $e) {
            return $this->getDefaultSettings();
        }
    }

    protected function getDefaultSettings(): array
    {
        return [
            'acs_url' => config('app.url') . '/tr069',
            'acs_username' => 'acs_admin',
            'acs_password' => '',
            'inform_interval' => 3600,
            'connection_request_username' => 'cpe',
            'connection_request_password' => '',
            'tr069_ssl_enabled' => true,
            'tr069_auth_method' => 'digest',
            'session_timeout' => 7200,
            'max_devices' => 100000,
            'log_level' => 'info',
            'enable_debug' => false,
        ];
    }

    protected function saveSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }

    protected function getServerInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_time' => now()->format('Y-m-d H:i:s T'),
            'timezone' => config('app.timezone'),
            'app_url' => config('app.url'),
            'db_connection' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
        ];
    }
}
