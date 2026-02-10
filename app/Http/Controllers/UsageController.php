<?php

namespace App\Http\Controllers;

use App\Services\GitHubCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(
        private GitHubCopilotService $githubService
    ) {}

    /**
     * Get the latest cached usage data.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $snapshot = $user->latestSnapshot();

        if (!$snapshot) {
            // No cached data, fetch fresh
            $snapshot = $this->githubService->checkAndStoreUsage($user);
        }

        if (!$snapshot) {
            return response()->json([
                'error' => 'Could not fetch usage data',
                'message' => 'Failed to retrieve Copilot usage. User may have unlimited quota.',
            ], 404);
        }

        return response()->json([
            'username' => $user->github_username,
            'copilot_plan' => $user->copilot_plan,
            'usage' => [
                'quota_limit' => $snapshot->quota_limit,
                'remaining' => $snapshot->remaining,
                'used' => $snapshot->used,
                'percent_remaining' => (float) $snapshot->percent_remaining,
                'reset_date' => $snapshot->reset_date->toDateString(),
            ],
            'checked_at' => $snapshot->checked_at->toIso8601String(),
            'cached' => true,
        ]);
    }

    /**
     * Force a fresh check from GitHub API.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $snapshot = $this->githubService->checkAndStoreUsage($user);

        if (!$snapshot) {
            return response()->json([
                'error' => 'Could not fetch usage data',
                'message' => 'Failed to retrieve Copilot usage from GitHub.',
            ], 502);
        }

        return response()->json([
            'username' => $user->github_username,
            'copilot_plan' => $user->copilot_plan,
            'usage' => [
                'quota_limit' => $snapshot->quota_limit,
                'remaining' => $snapshot->remaining,
                'used' => $snapshot->used,
                'percent_remaining' => (float) $snapshot->percent_remaining,
                'reset_date' => $snapshot->reset_date->toDateString(),
            ],
            'checked_at' => $snapshot->checked_at->toIso8601String(),
            'cached' => false,
        ]);
    }

    /**
     * Get today's usage data (to calculate daily delta).
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $snapshots = $user->todaySnapshots()->get();

        if ($snapshots->isEmpty()) {
            // Fetch fresh data if nothing today
            $this->githubService->checkAndStoreUsage($user);
            $snapshots = $user->todaySnapshots()->get();
        }

        $firstSnapshot = $snapshots->first();
        $latestSnapshot = $snapshots->last();

        $usedToday = 0;
        if ($firstSnapshot && $latestSnapshot && $firstSnapshot->id !== $latestSnapshot->id) {
            $usedToday = $firstSnapshot->remaining - $latestSnapshot->remaining;
            if ($usedToday < 0) {
                // Quota may have reset
                $usedToday = 0;
            }
        }

        return response()->json([
            'username' => $user->github_username,
            'date' => now()->toDateString(),
            'used_today' => $usedToday,
            'current' => $latestSnapshot ? [
                'remaining' => $latestSnapshot->remaining,
                'used' => $latestSnapshot->used,
                'quota_limit' => $latestSnapshot->quota_limit,
                'percent_remaining' => (float) $latestSnapshot->percent_remaining,
            ] : null,
            'snapshots_today' => $snapshots->count(),
            'first_check' => $firstSnapshot?->checked_at?->toIso8601String(),
            'last_check' => $latestSnapshot?->checked_at?->toIso8601String(),
        ]);
    }

    /**
     * Get usage history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 50), 200);
        $days = min((int) $request->query('days', 7), 30);

        $snapshots = $user->usageSnapshots()
            ->where('checked_at', '>=', now()->subDays($days))
            ->orderBy('checked_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'username' => $user->github_username,
            'history' => $snapshots->map(fn($s) => [
                'quota_limit' => $s->quota_limit,
                'remaining' => $s->remaining,
                'used' => $s->used,
                'percent_remaining' => (float) $s->percent_remaining,
                'reset_date' => $s->reset_date->toDateString(),
                'checked_at' => $s->checked_at->toIso8601String(),
            ]),
            'count' => $snapshots->count(),
        ]);
    }
}
