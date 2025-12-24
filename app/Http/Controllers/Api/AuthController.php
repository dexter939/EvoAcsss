<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\TenantAwareTokenService;
use Illuminate\Validation\ValidationException;

/**
 * API Authentication Controller
 * 
 * Handles mobile app authentication using API tokens
 */
class AuthController extends Controller
{
    public function __construct(
        protected TenantAwareTokenService $tokenService
    ) {}

    /**
     * Login user and generate API token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();

        $newToken = $this->tokenService->createToken($user, 'mobile-app');

        return response()->json([
            'token' => $newToken->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'message' => 'Login successful'
        ], 200);
    }

    /**
     * Logout user and revoke token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ], 200);
    }

    /**
     * Get authenticated user info
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ], 200);
    }

    /**
     * Refresh token (optional - generates new token)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();

        $newToken = $this->tokenService->createToken($user, 'mobile-app');

        return response()->json([
            'token' => $newToken->plainTextToken,
            'tenant_id' => $user->tenant_id,
            'message' => 'Token refreshed'
        ], 200);
    }
}
