<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiKeyAuth - Middleware per autenticazione API tramite chiave
 * ApiKeyAuth - Middleware for API authentication via key
 * 
 * Protegge gli endpoints API richiedendo una chiave API valida
 * Protects API endpoints by requiring a valid API key
 * 
 * Modalità autenticazione / Authentication modes:
 * - Header: X-API-Key: your-api-key
 * - Query string: ?api_key=your-api-key
 * 
 * Configurazione / Configuration:
 * - Chiave API configurabile via variabile d'ambiente ACS_API_KEY
 * - Default: "acs-secret-key-change-in-production" (CAMBIARE IN PRODUZIONE!)
 * - API key configurable via environment variable ACS_API_KEY
 * - Default: "acs-secret-key-change-in-production" (CHANGE IN PRODUCTION!)
 */
class ApiKeyAuth
{
    /**
     * Gestisce richiesta HTTP verificando API key
     * Handle HTTP request by verifying API key
     * 
     * @param Request $request Richiesta HTTP / HTTP request
     * @param Closure $next Prossimo middleware / Next middleware
     * @return Response Risposta HTTP / HTTP response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica se utente è già autenticato tramite sessione web
        // Check if user is already authenticated via web session
        if (auth()->check()) {
            // Autenticato tramite sessione Laravel, procedi
            // Authenticated via Laravel session, proceed
            return $next($request);
        }
        
        // Se non autenticato via sessione, richiede API key
        // If not authenticated via session, require API key
        
        // Estrae API key da header X-API-Key o query parameter api_key
        // Extract API key from X-API-Key header or api_key query parameter
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
        
        // Carica chiave API valida da variabile d'ambiente
        // Load valid API key from environment variable
        $validApiKey = env('ACS_API_KEY', 'acs-secret-key-change-in-production');
        
        // Verifica presenza e validità API key
        // Verify presence and validity of API key
        if (!$apiKey || $apiKey !== $validApiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required. Please login or provide valid API key in X-API-Key header.'
            ], 401);
        }
        
        // API key valida, prosegue con la richiesta
        // Valid API key, proceed with request
        return $next($request);
    }
}
