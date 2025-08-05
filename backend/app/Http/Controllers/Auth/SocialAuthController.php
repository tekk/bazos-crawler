<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Sanctum\PersonalAccessToken;

class SocialAuthController extends Controller
{
    /**
     * Redirect to OAuth provider
     */
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);
        
        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    /**
     * Handle OAuth callback
     */
    public function callback(string $provider, Request $request)
    {
        $this->validateProvider($provider);
        
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            
            // Find or create user
            $user = $this->findOrCreateUser($socialUser, $provider);
            
            // Generate API token
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;
            
            // Log the login
            activity()
                ->causedBy($user)
                ->log('User logged in via ' . ucfirst($provider));
            
            // Update last activity
            $user->update(['last_activity_at' => now()]);
            
            return response()->json([
                'success' => true,
                'user' => $user->load('roles', 'permissions'),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Social auth error: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 401);
        }
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            activity()
                ->causedBy($user)
                ->log('User logged out');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke all tokens
            $user->tokens()->delete();
            
            activity()
                ->causedBy($user)
                ->log('User logged out from all devices');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out from all devices'
        ]);
    }

    /**
     * Get current user info
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'user' => $user->load('roles', 'permissions', 'crawlerSearches.foundItems'),
            'stats' => [
                'active_searches' => $user->crawlerSearches()->active()->count(),
                'total_items' => $user->foundItems()->count(),
                'recent_items' => $user->foundItems()->recent()->count(),
                'search_quota' => $user->getSearchQuota(),
                'can_create_search' => $user->canCreateSearch(),
            ]
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addDays(30)->toISOString(),
        ]);
    }

    /**
     * Find or create user from social provider
     */
    private function findOrCreateUser($socialUser, string $provider): User
    {
        // First, try to find by provider ID
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();
        
        if ($user) {
            // Update user info if needed
            $user->update([
                'name' => $socialUser->getName() ?: $user->name,
                'email' => $socialUser->getEmail() ?: $user->email,
                'avatar' => $socialUser->getAvatar() ?: $user->avatar,
                'provider_token' => $socialUser->token,
            ]);
            
            return $user;
        }
        
        // Try to find by email
        $user = User::where('email', $socialUser->getEmail())->first();
        
        if ($user) {
            // Link social account to existing user
            $user->update([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'provider_token' => $socialUser->token,
                'avatar' => $socialUser->getAvatar() ?: $user->avatar,
            ]);
            
            return $user;
        }
        
        // Create new user
        $user = User::create([
            'name' => $socialUser->getName() ?: 'User',
            'email' => $socialUser->getEmail(),
            'email_verified_at' => now(),
            'avatar' => $socialUser->getAvatar(),
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_token' => $socialUser->token,
            'password' => Hash::make(Str::random(32)), // Random password for security
            'is_active' => true,
        ]);
        
        // Assign default role
        $user->assignRole('user');
        
        // Create default notification settings
        $user->notificationSettings()->create([
            'email_notifications' => true,
            'push_notifications' => true,
            'new_items' => true,
            'price_drops' => true,
            'weekly_summary' => true,
        ]);
        
        activity()
            ->causedBy($user)
            ->log('New user registered via ' . ucfirst($provider));
        
        return $user;
    }

    /**
     * Validate OAuth provider
     */
    private function validateProvider(string $provider): void
    {
        $allowedProviders = ['google', 'facebook', 'apple'];
        
        if (!in_array($provider, $allowedProviders)) {
            abort(404, 'Invalid OAuth provider');
        }
    }
}