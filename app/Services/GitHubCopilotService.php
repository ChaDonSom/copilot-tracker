<?php

namespace App\Services;

use App\Models\User;
use App\Models\UsageSnapshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubCopilotService
{
    private const GITHUB_API_BASE = 'https://api.github.com';
    private const COPILOT_ENDPOINT = '/copilot_internal/user';

    /**
     * Validate a GitHub token and return user info.
     *
     * @return array{valid: bool, username?: string, error?: string}
     */
    public function validateToken(string $token): array
    {
        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get(self::GITHUB_API_BASE . '/user');

            if ($response->successful()) {
                return [
                    'valid' => true,
                    'username' => $response->json('login'),
                ];
            }

            return [
                'valid' => false,
                'error' => 'Invalid token or unauthorized',
            ];
        } catch (\Exception $e) {
            Log::error('GitHub token validation failed', ['error' => $e->getMessage()]);
            return [
                'valid' => false,
                'error' => 'Failed to connect to GitHub API',
            ];
        }
    }

    /**
     * Fetch Copilot usage data for a given token.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function fetchUsage(string $token): array
    {
        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get(self::GITHUB_API_BASE . self::COPILOT_ENDPOINT);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $this->parseUsageData($data),
                    'raw' => $data,
                ];
            }

            if ($response->status() === 404 || $response->status() === 403) {
                return [
                    'success' => false,
                    'error' => 'Copilot access not available for this account',
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch usage data: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('GitHub Copilot usage fetch failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Failed to connect to GitHub API',
            ];
        }
    }

    /**
     * Parse raw usage data into a normalized format.
     */
    private function parseUsageData(array $data): array
    {
        $premiumInteractions = $data['quota_snapshots']['premium_interactions'] ?? [];

        return [
            'username' => $data['login'] ?? null,
            'copilot_plan' => $data['copilot_plan'] ?? null,
            'quota_limit' => $premiumInteractions['entitlement'] ?? 0,
            'remaining' => $premiumInteractions['remaining'] ?? 0,
            'used' => ($premiumInteractions['entitlement'] ?? 0) - ($premiumInteractions['remaining'] ?? 0),
            'percent_remaining' => $premiumInteractions['percent_remaining'] ?? 0,
            'unlimited' => $premiumInteractions['unlimited'] ?? false,
            'reset_date' => $data['quota_reset_date'] ?? null,
            'reset_date_utc' => $data['quota_reset_date_utc'] ?? null,
        ];
    }

    /**
     * Check usage and store snapshot for a user.
     */
    public function checkAndStoreUsage(User $user): ?UsageSnapshot
    {
        // Prefer OAuth token if available, fall back to PAT
        $token = $user->github_oauth_token ?? $user->github_token;
        
        if (!$token) {
            Log::warning('No GitHub token available for user', [
                'user_id' => $user->id,
                'username' => $user->github_username,
            ]);
            return null;
        }

        $result = $this->fetchUsage($token);

        if (!$result['success']) {
            Log::warning('Failed to fetch usage for user', [
                'user_id' => $user->id,
                'username' => $user->github_username,
                'error' => $result['error'],
            ]);
            return null;
        }

        $data = $result['data'];

        // Skip if unlimited
        if ($data['unlimited']) {
            Log::info('User has unlimited quota', ['username' => $user->github_username]);
            return null;
        }

        // Update user info
        $user->update([
            'copilot_plan' => $data['copilot_plan'],
            'quota_limit' => $data['quota_limit'],
            'quota_reset_date' => $data['reset_date'],
            'last_checked_at' => now(),
        ]);

        // Create snapshot
        return UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => $data['quota_limit'],
            'remaining' => $data['remaining'],
            'used' => $data['used'],
            'percent_remaining' => $data['percent_remaining'],
            'reset_date' => $data['reset_date'],
            'checked_at' => now(),
        ]);
    }

    /**
     * Find or create a user from a GitHub token.
     */
    public function findOrCreateUser(string $token): ?User
    {
        try {
            $validation = $this->validateToken($token);

            if (!$validation['valid']) {
                Log::warning('Token validation failed', ['error' => $validation['error'] ?? 'Unknown']);
                return null;
            }

            $username = $validation['username'];

            Log::info('Finding or creating user', ['username' => $username]);

            return User::updateOrCreate(
                ['github_username' => $username],
                ['github_token' => $token]
            );
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Encryption error - APP_KEY may be misconfigured', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Encryption error: APP_KEY may be misconfigured or changed');
        } catch (\Exception $e) {
            Log::error('Failed to find or create user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
