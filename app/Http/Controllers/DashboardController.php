<?php

namespace App\Http\Controllers;

use App\Models\UsageSnapshot;
use App\Services\GitHubCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private GitHubCopilotService $githubService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // Fetch latest snapshot or create one
        $latestSnapshot = $user->latestSnapshot();
        if (!$latestSnapshot) {
            $latestSnapshot = $this->githubService->checkAndStoreUsage($user);
        }

        // Get historical data for chart (last 30 days)
        $history = $user->usageSnapshots()
            ->where('checked_at', '>=', now()->subDays(30))
            ->orderBy('checked_at', 'asc')
            ->get();

        $viewData = $this->prepareChartDataFromHistory($history, $latestSnapshot, $user);

        return view('dashboard', $viewData);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Force a fresh check from GitHub API
        $latestSnapshot = $this->githubService->checkAndStoreUsage($user);

        // If the API call failed, return an error
        if (!$latestSnapshot) {
            return response()->json([
                'error' => 'Failed to fetch usage data from GitHub. Please try again later.'
            ], 500);
        }

        // Get historical data for chart (last 30 days)
        $history = $user->usageSnapshots()
            ->where('checked_at', '>=', now()->subDays(30))
            ->orderBy('checked_at', 'asc')
            ->get();

        $viewData = $this->prepareChartDataFromHistory($history, $latestSnapshot, $user);
        $viewData['snapshot'] = $this->snapshotToArray($viewData['snapshot']);

        return response()->json($viewData);
    }

    private function prepareChartDataFromHistory($history, $latestSnapshot, $user): array
    {
        // Calculate daily usage
        $dailyUsage = [];
        $historyByDate = $history->groupBy(fn($snapshot) => $snapshot->checked_at->format('Y-m-d'));

        foreach ($historyByDate as $date => $snapshots) {
            $firstSnapshot = $snapshots->first();
            $lastSnapshot = $snapshots->last();

            $used = $firstSnapshot->remaining - $lastSnapshot->remaining;
            if ($used < 0) {
                $used = 0; // Quota reset
            }

            $dailyUsage[$date] = [
                'date' => $date,
                'used' => $used,
                'remaining' => $lastSnapshot->remaining,
                'total' => $lastSnapshot->quota_limit,
            ];
        }

        // Calculate recommendation data
        $recommendationData = $this->calculateRecommendation($latestSnapshot, $history);

        // Prepare chart data (daily view)
        $chartData = [
            'labels' => array_keys($dailyUsage),
            'used' => array_column($dailyUsage, 'used'),
            'recommendation' => $recommendationData['dailyRecommendationLine'],
        ];

        // Prepare per-check chart data
        $perCheckData = $this->preparePerCheckData($history);

        return [
            'user' => $user,
            'snapshot' => $latestSnapshot,
            'chartData' => $chartData,
            'perCheckData' => $perCheckData,
            'dailyUsage' => $dailyUsage,
            'recommendation' => $recommendationData,
        ];
    }

    private function calculateRecommendation($snapshot, $history)
    {
        if (!$snapshot) {
            return [
                'dailyRecommended' => 0,
                'daysRemaining' => 0,
                'totalRecommendedByNow' => 0,
                'dailyRecommendationLine' => [],
            ];
        }

        $resetDate = $snapshot->reset_date;
        $now = now();
        $daysRemaining = max(1, $now->diffInDays($resetDate, false));

        // Calculate recommended daily usage
        $dailyRecommended = $daysRemaining > 0 ? round($snapshot->remaining / $daysRemaining, 2) : 0;

        // Calculate how much should have been used by now based on even distribution
        $resetDateStart = $resetDate->copy()->subDays(30); // Assuming 30-day cycle
        $totalDaysInCycle = max(1, $resetDateStart->diffInDays($resetDate));
        $daysPassed = max(0, $resetDateStart->diffInDays($now));
        $dailyIdealUsage = $snapshot->quota_limit / $totalDaysInCycle;
        $totalRecommendedByNow = round($daysPassed * $dailyIdealUsage);

        // Build recommendation line for the chart (cumulative usage trajectory)
        $dailyRecommendationLine = [];
        $historyByDate = $history->groupBy(fn($s) => $s->checked_at->format('Y-m-d'));

        $dailyRecommendationLine = array_fill(
            0,
            count($historyByDate),
            round($dailyIdealUsage, 2)
        );

        return [
            'dailyRecommended' => $dailyRecommended,
            'daysRemaining' => $daysRemaining,
            'totalRecommendedByNow' => $totalRecommendedByNow,
            'dailyIdealUsage' => round($dailyIdealUsage, 2),
            'dailyRecommendationLine' => $dailyRecommendationLine,
        ];
    }

    private function preparePerCheckData($history)
    {
        $labels = [];
        $timestamps = [];
        $used = [];
        $recommendation = [];

        $cumulativeUsed = 0;
        $firstSnapshot = $history->first();
        $lastSnapshot = $history->last();

        // Calculate the current total used from the latest snapshot
        $currentTotalUsed = $lastSnapshot ? $lastSnapshot->used : 0;

        foreach ($history as $index => $snapshot) {
            $labels[] = $snapshot->checked_at->format('M d H:i');
            $timestamps[] = $snapshot->checked_at->toIso8601String();

            if ($index === 0) {
                $used[] = 0;
            } else {
                $prevSnapshot = $history[$index - 1];
                $delta = $prevSnapshot->remaining - $snapshot->remaining;
                if ($delta < 0) {
                    $delta = 0; // Quota reset
                }
                $cumulativeUsed += $delta;
                $used[] = $cumulativeUsed;
            }

            // Calculate recommendation line (ideal trajectory)
            if ($firstSnapshot) {
                // Calculate cycle start from reset date (30 days back)
                $resetDate = $snapshot->reset_date;
                $cycleStart = $resetDate->copy()->subDays(30);
                $totalDaysInCycle = 30;
                $dailyIdealUsage = $snapshot->quota_limit / $totalDaysInCycle;

                // Calculate elapsed time from cycle start - use ->copy() to avoid mutating the original
                $elapsedDays = $cycleStart->diffInDays($snapshot->checked_at->copy()->startOfDay(), false);
                $elapsedHours = $snapshot->checked_at->hour + ($snapshot->checked_at->minute / 60);
                $totalElapsedDays = $elapsedDays + ($elapsedHours / 24);

                $recommendation[] = round($totalElapsedDays * $dailyIdealUsage);
            }
        }

        // Adjust the used values to reflect actual position: currentTotalUsed - cumulativeUsed to currentTotalUsed
        // This means we're showing from (currentTotalUsed - totalTracked) to currentTotalUsed
        $totalTracked = $cumulativeUsed;
        $baselineUsed = $currentTotalUsed - $totalTracked;

        $used = array_map(fn($val) => $baselineUsed + $val, $used);

        return [
            'labels' => $labels,
            'timestamps' => $timestamps,
            'used' => $used,
            'recommendation' => $recommendation,
        ];
    }

    private function snapshotToArray(UsageSnapshot $snapshot): array
    {
        return [
            'quota_limit' => (int) $snapshot->quota_limit,
            'remaining' => (int) $snapshot->remaining,
            'used' => (int) $snapshot->used,
            'percent_remaining' => (float) $snapshot->percent_remaining,
            'reset_date' => $snapshot->reset_date?->toIso8601String(),
            'checked_at' => $snapshot->checked_at?->toIso8601String(),
        ];
    }
}
