<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API Authentication Middleware
 * 
 * Supports both:
 * 1. API Key authentication (X-API-Key header) - for server-to-server
 * 2. Sanctum token authentication (Bearer token) - for mobile apps
 */
class ApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        // First, try Sanctum authentication (Bearer token)
        // Sanctum middleware runs before this, so check if user is already authenticated
        $user = $request->user('sanctum');
        
        if ($user) {
            // User authenticated via Sanctum token
            return $next($request);
        }

        // Fallback to API Key authentication (for server-to-server calls)
        $apiKey = $request->header('X-API-Key');

        if ($apiKey) {
            // Validate API key
            $validKeys = [
                'acs-dev-test-key-2024',
                config('services.acs.api_key', 'acs-secret-key-2024'),
            ];

            if (in_array($apiKey, $validKeys)) {
                return $next($request);
            }

            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Invalid API key.'
            ], 403);
        }

        // No authentication provided
        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Authentication required. Provide Bearer token or X-API-Key header.'
        ], 401);
    }
}
