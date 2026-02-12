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

    private const ALLOWED_RANGES = [0, 1, 7, 30]; // 0 = today only
    private const MAX_OFFSET = 52; // ~1.5 years back at 30-day range

    public function index(Request $request): View
    {
        $user = $request->user();

        // Fetch latest snapshot or create one
        $latestSnapshot = $user->latestSnapshot();
        if (!$latestSnapshot) {
            $latestSnapshot = $this->githubService->checkAndStoreUsage($user);
        }

        // Chart range params (validated)
        [$rangeDays, $offset] = $this->validateRangeParams($request);

        $history = $this->getHistoryForRange($user, $rangeDays, $offset);

        $viewData = $this->prepareChartDataFromHistory($history, $latestSnapshot, $user);
        $viewData['todayUsed'] = $this->calculateTodayUsed($user);
        $viewData['chartRange'] = $rangeDays;
        $viewData['chartOffset'] = $offset;
        $viewData['chartRangeLabel'] = $this->buildChartRangeLabel($rangeDays, $offset, $user);
        $viewData['percentUsed'] = $latestSnapshot && $latestSnapshot->quota_limit > 0
            ? round(($latestSnapshot->used / $latestSnapshot->quota_limit) * 100, 1)
            : ($latestSnapshot ? round(100 - $latestSnapshot->percent_remaining, 1) : 0);
        $viewData['paceStatus'] = $this->calculatePaceStatus($latestSnapshot);
        $viewData['lastCheckedAt'] = $latestSnapshot?->checked_at;

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

        // Chart range params (validated)
        [$rangeDays, $offset] = $this->validateRangeParams($request);

        $history = $this->getHistoryForRange($user, $rangeDays, $offset);

        $viewData = $this->prepareChartDataFromHistory($history, $latestSnapshot, $user);
        $viewData['snapshot'] = $this->snapshotToArray($viewData['snapshot']);
        $viewData['todayUsed'] = $this->calculateTodayUsed($user);
        $viewData['chartRange'] = $rangeDays;
        $viewData['chartOffset'] = $offset;
        $viewData['paceStatus'] = $this->calculatePaceStatus($latestSnapshot);
        $viewData['lastCheckedAt'] = $latestSnapshot->checked_at?->toIso8601String();

        return response()->json($viewData);
    }

    private function getHistoryForRange($user, int $rangeDays, int $offset)
    {
        [$start, $end] = $this->calculateDateRange($rangeDays, $offset, $user);

        return $user->usageSnapshots()
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->orderBy('checked_at', 'asc')
            ->get();
    }

    /**
     * Calculate usage from a collection of snapshots by taking the difference
     * between the first and last "remaining" values, clamped at 0.
     */
    private function calculateUsageFromSnapshots($snapshots): int
    {
        if ($snapshots->count() < 2) {
            return 0;
        }

        // Usage = first snapshot's remaining - last snapshot's remaining
        // (remaining decreases as requests are consumed)
        $first = $snapshots->first();
        $last = $snapshots->last();
        $used = $first->remaining - $last->remaining;

        return max(0, $used);
    }

    private function calculateTodayUsed($user): int
    {
        return $this->calculateUsageFromSnapshots($user->todaySnapshots()->get());
    }

    private function buildChartRangeLabel(int $rangeDays, int $offset, $user = null): string
    {
        // Special case: range=0 means "today"
        if ($rangeDays === 0) {
            if ($offset === 0) {
                return 'Today';
            } elseif ($offset === 1) {
                return 'Yesterday';
            } else {
                [$start, $end] = $this->calculateDateRange($rangeDays, $offset, $user);
                return $start->format('M d');
            }
        }
        
        if ($offset === 0) {
            return 'Last ' . $rangeDays . ' day' . ($rangeDays > 1 ? 's' : '');
        }

        [$start, $end] = $this->calculateDateRange($rangeDays, $offset, $user);

        return $start->format('M d') . ' – ' . $end->format('M d');
    }

    /**
     * Calculate the calendar-day-aligned start and end dates for a given range and offset.
     * Returns [startOfDay, startOfNextDay) so the upper bound is exclusive.
     * Uses user's timezone for date boundaries.
     * Special case: range=0 means "today only" (current calendar day in user's timezone).
     */
    private function calculateDateRange(int $rangeDays, int $offset, $user = null): array
    {
        $timezone = $user ? $user->getUserTimezone() : 'UTC';
        
        // Special case: range=0 means "today only"
        if ($rangeDays === 0) {
            // For offset=0, show today. For offset=1, show yesterday, etc.
            $start = now($timezone)->subDays($offset)->startOfDay();
            $end = $start->copy()->endOfDay()->addSecond(); // Exclusive upper bound
            return [$start, $end];
        }
        
        // Regular range calculation (last N days)
        $end = now($timezone)->subDays($offset * $rangeDays)->addDay()->startOfDay();
        $start = $end->copy()->subDays($rangeDays);

        return [$start, $end];
    }

    private function validateRangeParams(Request $request): array
    {
        $rangeDays = (int) $request->input('range', 30);
        if (!in_array($rangeDays, self::ALLOWED_RANGES, true)) {
            $rangeDays = 30;
        }

        $offset = max(0, min(self::MAX_OFFSET, (int) $request->input('offset', 0)));

        return [$rangeDays, $offset];
    }

    public function chartData(Request $request): JsonResponse
    {
        $user = $request->user();
        $latestSnapshot = $user->latestSnapshot();

        [$rangeDays, $offset] = $this->validateRangeParams($request);

        $history = $this->getHistoryForRange($user, $rangeDays, $offset);
        $viewData = $this->prepareChartDataFromHistory($history, $latestSnapshot, $user);

        return response()->json([
            'chartData' => $viewData['chartData'],
            'perCheckData' => $viewData['perCheckData'],
            'chartRange' => $rangeDays,
            'chartOffset' => $offset,
        ]);
    }

    private function prepareChartDataFromHistory($history, $latestSnapshot, $user): array
    {
        // Calculate daily usage
        $dailyUsage = [];
        $timezone = $user->getUserTimezone();
        $historyByDate = $history->groupBy(fn($snapshot) => $snapshot->checked_at->copy()->setTimezone($timezone)->format('Y-m-d'));

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
        $perCheckData = $this->preparePerCheckData($history, $user);

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
                'endOfDayUsage' => 0,
                'endOfDayPercentageLeft' => null,
            ];
        }

        $resetDate = $snapshot->reset_date;
        $now = now();
        $daysRemaining = (int) ceil(max(1, $now->diffInDays($resetDate, false)));

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

        // Calculate end-of-day percentage left
        // End-of-day usage = used this month + recommended daily usage
        $endOfDayUsage = $snapshot->used + $dailyRecommended;
        // Percentage left = 100 - ((end-of-day usage / quota limit) * 100)
        $endOfDayPercentageLeft = $snapshot->quota_limit > 0
            ? round(100 - (($endOfDayUsage / $snapshot->quota_limit) * 100), 2)
            : null;

        return [
            'dailyRecommended' => $dailyRecommended,
            'daysRemaining' => $daysRemaining,
            'totalRecommendedByNow' => $totalRecommendedByNow,
            'dailyIdealUsage' => round($dailyIdealUsage, 2),
            'dailyRecommendationLine' => $dailyRecommendationLine,
            'endOfDayUsage' => round($endOfDayUsage, 2),
            'endOfDayPercentageLeft' => $endOfDayPercentageLeft,
        ];
    }

    private function preparePerCheckData($history, $user)
    {
        $labels = [];
        $timestamps = [];
        $used = [];
        $recommendation = [];

        $timezone = $user->getUserTimezone();
        $cumulativeUsed = 0;
        $firstSnapshot = $history->first();
        $lastSnapshot = $history->last();

        // Calculate the current total used from the latest snapshot
        $currentTotalUsed = $lastSnapshot ? $lastSnapshot->used : 0;

        foreach ($history as $index => $snapshot) {
            // Convert to user timezone for display
            $checkedAtInUserTz = $snapshot->checked_at->copy()->setTimezone($timezone);
            $labels[] = $checkedAtInUserTz->format('M d H:i');
            $timestamps[] = $snapshot->checked_at->toIso8601String();

            if ($index === 0) {
                $used[] = 0;
            } else {
                $prevSnapshot = $history[$index - 1];
                $delta = $prevSnapshot->remaining - $snapshot->remaining;
                if ($delta < 0) {
                    // Quota reset detected - reset tracking to prevent negative baseline
                    $cumulativeUsed = 0;
                    $delta = 0;
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

                // Calculate elapsed time from cycle start in user's timezone
                $snapshotInUserTz = $snapshot->checked_at->copy()->setTimezone($timezone);
                $elapsedDays = $cycleStart->diffInDays($snapshotInUserTz->copy()->startOfDay(), false);
                $elapsedHours = $snapshotInUserTz->hour + ($snapshotInUserTz->minute / 60);
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

    /**
     * Calculate the user's usage pace status relative to ideal trajectory.
     * Returns pace difference (positive = under pace/good, negative = over pace/warning).
     */
    private function calculatePaceStatus($snapshot): ?array
    {
        if (!$snapshot || !$snapshot->quota_limit || $snapshot->quota_limit <= 0) {
            return null;
        }

        $resetDate = $snapshot->reset_date;
        $now = now();

        // Calculate cycle boundaries
        $cycleStart = $resetDate->copy()->subDays(30); // Assuming 30-day cycle
        $totalDaysInCycle = 30;
        $daysPassed = max(0, $cycleStart->diffInDays($now, false));

        // Add fractional day for current time, clamped to cycle length
        $hoursToday = $now->hour + ($now->minute / 60);
        $totalElapsedDays = min($totalDaysInCycle, $daysPassed + ($hoursToday / 24));

        // Calculate ideal usage at this point in the cycle
        $dailyIdealUsage = $snapshot->quota_limit / $totalDaysInCycle;
        $idealUsedByNow = round($totalElapsedDays * $dailyIdealUsage);

        // Actual usage
        $actualUsed = $snapshot->used;

        // Difference: positive means under pace (good), negative means over pace (warning)
        $paceDifference = $idealUsedByNow - $actualUsed;

        // Determine status label
        $threshold = max(1, round($dailyIdealUsage * 0.1)); // 10% of daily ideal as "on pace" zone
        if (abs($paceDifference) <= $threshold) {
            $status = 'on-pace';
        } elseif ($paceDifference > 0) {
            $status = 'under-pace';
        } else {
            $status = 'over-pace';
        }

        return [
            'status' => $status,
            'difference' => (int) $paceDifference,
            'idealUsedByNow' => (int) $idealUsedByNow,
            'actualUsed' => (int) $actualUsed,
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

    public function updateTimezone(Request $request): JsonResponse
    {
        $request->validate([
            'timezone' => 'required|string|timezone:all',
        ]);

        $user = $request->user();
        $user->timezone = $request->input('timezone');
        $user->save();

        return response()->json([
            'success' => true,
            'timezone' => $user->timezone,
        ]);
    }
}
