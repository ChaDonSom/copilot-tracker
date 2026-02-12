<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsageSnapshot;
use App\Services\GitHubCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPaceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithSnapshot(array $snapshotOverrides = []): array
    {
        $user = User::forceCreate([
            'github_username' => 'testuser',
            'github_token' => null,
            'copilot_plan' => 'individual_pro',
            'quota_limit' => 300,
            'quota_reset_date' => now()->addDays(15),
            'last_checked_at' => now(),
        ]);

        $snapshotData = array_merge([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 150,
            'used' => 150,
            'percent_remaining' => 50.00,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => now(),
        ], $snapshotOverrides);

        $snapshot = UsageSnapshot::create($snapshotData);

        return [$user, $snapshot];
    }

    public function test_dashboard_includes_pace_status_data(): void
    {
        [$user, $snapshot] = $this->createUserWithSnapshot();

        // Mock the service to avoid real API calls
        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('paceStatus');

        $paceStatus = $response->viewData('paceStatus');
        $this->assertNotNull($paceStatus);
        $this->assertArrayHasKey('status', $paceStatus);
        $this->assertArrayHasKey('difference', $paceStatus);
        $this->assertArrayHasKey('idealUsedByNow', $paceStatus);
        $this->assertArrayHasKey('actualUsed', $paceStatus);
        $this->assertContains($paceStatus['status'], ['on-pace', 'under-pace', 'over-pace']);
    }

    public function test_dashboard_includes_last_checked_at(): void
    {
        [$user, $snapshot] = $this->createUserWithSnapshot();

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('lastCheckedAt');
        $this->assertNotNull($response->viewData('lastCheckedAt'));
    }

    public function test_pace_status_shows_over_pace_when_usage_exceeds_ideal(): void
    {
        // User has used 250 out of 300, but only half the cycle has passed
        // Reset date is in 15 days (half-cycle point), so ideal would be ~150
        [$user, $snapshot] = $this->createUserWithSnapshot([
            'used' => 250,
            'remaining' => 50,
            'percent_remaining' => 16.67,
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $paceStatus = $response->viewData('paceStatus');
        $this->assertNotNull($paceStatus);
        // Used 250 when ideal would be ~150, so should be over pace
        $this->assertEquals('over-pace', $paceStatus['status']);
        $this->assertLessThan(0, $paceStatus['difference']);
    }

    public function test_pace_status_shows_under_pace_when_usage_below_ideal(): void
    {
        // User has used 50 out of 300, but half the cycle has passed
        // Reset date is in 15 days, so ideal would be ~150
        [$user, $snapshot] = $this->createUserWithSnapshot([
            'used' => 50,
            'remaining' => 250,
            'percent_remaining' => 83.33,
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $paceStatus = $response->viewData('paceStatus');
        $this->assertNotNull($paceStatus);
        // Used 50 when ideal would be ~150, so should be under pace
        $this->assertEquals('under-pace', $paceStatus['status']);
        $this->assertGreaterThan(0, $paceStatus['difference']);
    }

    public function test_pace_status_null_when_no_snapshot(): void
    {
        $user = User::forceCreate([
            'github_username' => 'newuser',
            'github_token' => null,
            'copilot_plan' => null,
            'quota_limit' => null,
            'quota_reset_date' => null,
            'last_checked_at' => null,
        ]);

        // Mock the service to return null (no snapshot available)
        $this->mock(GitHubCopilotService::class, function ($mock) {
            $mock->shouldReceive('checkAndStoreUsage')->andReturn(null);
        });

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $paceStatus = $response->viewData('paceStatus');
        $this->assertNull($paceStatus);
    }

    public function test_refresh_includes_pace_status_and_last_checked(): void
    {
        [$user, $snapshot] = $this->createUserWithSnapshot();

        // Mock the service to return the same snapshot on refresh
        $this->mock(GitHubCopilotService::class, function ($mock) use ($snapshot) {
            $mock->shouldReceive('checkAndStoreUsage')->andReturn($snapshot);
        });

        $response = $this->actingAs($user)->postJson('/dashboard/refresh', [
            'range' => 30,
            'offset' => 0,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('paceStatus', $data);
        $this->assertArrayHasKey('lastCheckedAt', $data);
        $this->assertNotNull($data['paceStatus']);
        $this->assertArrayHasKey('status', $data['paceStatus']);
    }

    public function test_dashboard_renders_pace_status_card(): void
    {
        [$user, $snapshot] = $this->createUserWithSnapshot();

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Usage Pace');
        $response->assertSee('data-stat="pace-status"', false);
    }

    public function test_dashboard_renders_last_checked_timestamp(): void
    {
        [$user, $snapshot] = $this->createUserWithSnapshot();

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="lastChecked"', false);
    }

    public function test_pace_status_shows_on_pace_within_threshold(): void
    {
        // With quota_limit=300, dailyIdealUsage=10, threshold=max(1,round(10*0.1))=1
        // Setting used to match idealUsedByNow so difference is 0 → on-pace
        $this->travelTo(now()->startOfDay()->addHours(12));

        // Use startOfDay for reset_date to match the model's date cast (no time component)
        $resetDate = now()->addDays(15)->startOfDay();
        $cycleStart = $resetDate->copy()->subDays(30);
        $daysPassed = max(0, $cycleStart->diffInDays(now(), false));
        $hoursToday = now()->hour + (now()->minute / 60);
        $totalElapsed = min(30, $daysPassed + ($hoursToday / 24));
        $idealUsed = (int) round($totalElapsed * (300 / 30));

        $user = User::forceCreate([
            'github_username' => 'testuser',
            'github_token' => null,
            'copilot_plan' => 'individual_pro',
            'quota_limit' => 300,
            'quota_reset_date' => $resetDate,
            'last_checked_at' => now(),
        ]);

        UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 300 - $idealUsed,
            'used' => $idealUsed,
            'percent_remaining' => round((300 - $idealUsed) / 300 * 100, 2),
            'reset_date' => $resetDate->toDateString(),
            'checked_at' => now(),
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $paceStatus = $response->viewData('paceStatus');
        $this->assertNotNull($paceStatus);
        $this->assertEquals('on-pace', $paceStatus['status']);
    }

    public function test_pace_status_transitions_from_on_pace_to_under_pace_at_boundary(): void
    {
        // With quota_limit=300, dailyIdealUsage=10, threshold=1
        // If used is 2 less than ideal, difference=2 > threshold=1 → under-pace
        $this->travelTo(now()->startOfDay()->addHours(12));

        $resetDate = now()->addDays(15)->startOfDay();
        $cycleStart = $resetDate->copy()->subDays(30);
        $daysPassed = max(0, $cycleStart->diffInDays(now(), false));
        $hoursToday = now()->hour + (now()->minute / 60);
        $totalElapsed = min(30, $daysPassed + ($hoursToday / 24));
        $idealUsed = (int) round($totalElapsed * (300 / 30));

        // Set used to idealUsed - 2, so difference = 2 > threshold of 1
        $used = $idealUsed - 2;

        $user = User::forceCreate([
            'github_username' => 'testuser',
            'github_token' => null,
            'copilot_plan' => 'individual_pro',
            'quota_limit' => 300,
            'quota_reset_date' => $resetDate,
            'last_checked_at' => now(),
        ]);

        UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 300 - $used,
            'used' => $used,
            'percent_remaining' => round((300 - $used) / 300 * 100, 2),
            'reset_date' => $resetDate->toDateString(),
            'checked_at' => now(),
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $paceStatus = $response->viewData('paceStatus');
        $this->assertNotNull($paceStatus);
        $this->assertEquals('under-pace', $paceStatus['status']);
        $this->assertGreaterThan(0, $paceStatus['difference']);
    }

    public function test_pace_status_clamps_elapsed_days_at_cycle_boundary(): void
    {
        // Reset date is in the past (stale snapshot) — elapsed should be clamped to 30 days
        // idealUsedByNow should be at most quota_limit (300)
        [$user, $snapshot] = $this->createUserWithSnapshot([
            'used' => 280,
            'remaining' => 20,
            'percent_remaining' => 6.67,
            'reset_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');

        $paceStatus = $response->viewData('paceStatus');
        $this->assertNotNull($paceStatus);
        // idealUsedByNow should be clamped to 300 (full cycle), not exceed it
        $this->assertLessThanOrEqual(300, $paceStatus['idealUsedByNow']);
    }
}
