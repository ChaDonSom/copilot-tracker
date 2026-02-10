<?php

namespace App\Http\Controllers;

use App\Services\GitHubCopilotService;
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

        // Prepare chart data
        $chartData = [
            'labels' => array_keys($dailyUsage),
            'used' => array_column($dailyUsage, 'used'),
            'remaining' => array_column($dailyUsage, 'remaining'),
        ];

        return view('dashboard', [
            'user' => $user,
            'snapshot' => $latestSnapshot,
            'chartData' => $chartData,
            'dailyUsage' => $dailyUsage,
        ]);
    }
}
