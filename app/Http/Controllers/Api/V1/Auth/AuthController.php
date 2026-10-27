<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    /**
     * Mobile login: Authenticate user & issue Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->sendError('Invalid email or password.', null, 401);
        }

        // Optional device name identifier (e.g. "iPhone 15", "Samsung Galaxy S24")
        $deviceName = $request->input('device_name', 'mobile_app');
        $token = $user->createToken($deviceName)->plainTextToken;

        // Safely eager load relations if available
        if (method_exists($user, 'roles')) {
            rescue(fn() => $user->loadMissing('roles'), null, false);
        }
        if (method_exists($user, 'clients')) {
            rescue(fn() => $user->loadMissing('clients'), null, false);
        }

        return $this->sendResponse([
            'user'       => new UserResource($user),
            'token'      => $token,
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    /**
     * Fetch authenticated user's profile and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (method_exists($user, 'roles')) {
            rescue(fn() => $user->loadMissing('roles'), null, false);
        }
        if (method_exists($user, 'clients')) {
            rescue(fn() => $user->loadMissing('clients'), null, false);
        }

        return $this->sendResponse(new UserResource($user), 'User profile retrieved successfully.');
    }

    /**
     * Revoke the current access token (logout this device).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse(null, 'Logged out successfully.');
    }

    /**
     * Revoke all access tokens (logout all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->sendResponse(null, 'Logged out from all devices successfully.');
    }
}
