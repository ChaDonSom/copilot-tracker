<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToGithub(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user'])
            ->redirect();
    }

    public function handleGithubCallback(): RedirectResponse
    {
        try {
            $githubUser = Socialite::driver('github')->user();
            
            $user = User::firstOrCreate(
                ['github_username' => $githubUser->getNickname()],
                [
                    'github_oauth_token' => $githubUser->token,
                    'copilot_plan' => null,
                ]
            );

            // Update OAuth token if it changed, without overwriting any existing PAT
            if ($user->wasRecentlyCreated === false) {
                $user->github_oauth_token = $githubUser->token;
                $user->save();
            }

            Auth::login($user);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            \Log::error('GitHub OAuth authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('home')
                ->with('error', 'Failed to authenticate with GitHub. Please try again.');
        }
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        
        return redirect()->route('home');
    }
}
