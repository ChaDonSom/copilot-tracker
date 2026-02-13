<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsageSnapshot;
use App\Services\GitHubCopilotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithTimezone(string $timezone = 'America/New_York'): User
    {
        return User::forceCreate([
            'github_username' => 'testuser',
            'github_token' => null,
            'timezone' => $timezone,
            'copilot_plan' => 'individual_pro',
            'quota_limit' => 300,
            'quota_reset_date' => now()->addDays(15),
            'last_checked_at' => now(),
        ]);
    }

    public function test_user_timezone_defaults_to_utc(): void
    {
        $user = User::forceCreate([
            'github_username' => 'testuser',
            'github_token' => null,
        ]);

        $this->assertEquals('UTC', $user->getUserTimezone());
    }

    public function test_user_can_have_custom_timezone(): void
    {
        $user = $this->createUserWithTimezone('America/New_York');

        $this->assertEquals('America/New_York', $user->getUserTimezone());
    }

    public function test_dashboard_uses_user_timezone_for_date_range(): void
    {
        $userTimezone = 'America/New_York';
        $user = $this->createUserWithTimezone($userTimezone);

        // Create snapshots at different local times that cross midnight
        // First at 11 PM in the user's timezone, second at 1 AM the next local day.
        // These times are stored as UTC in the database, but the dashboard should
        // group/filter them according to the user's local day.
        
        $baseTimeLocal = \Carbon\Carbon::now($userTimezone)->subDays(1)->setTime(23, 0, 0); // Yesterday 11 PM local time

        $snapshot1CheckedAtUtc = $baseTimeLocal->copy()->setTimezone('UTC');
        $snapshot2CheckedAtUtc = $baseTimeLocal->copy()->addHours(2)->setTimezone('UTC'); // Yesterday 11 PM + 2 hrs = today 1 AM local

        $snapshot1 = UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 200,
            'used' => 100,
            'percent_remaining' => 66.67,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => $snapshot1CheckedAtUtc,
        ]);

        $snapshot2 = UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 150,
            'used' => 150,
            'percent_remaining' => 50.00,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => $snapshot2CheckedAtUtc,
        ]);

        $this->mock(GitHubCopilotService::class);

        // Use 7-day range to ensure our snapshots are included
        $response = $this->actingAs($user)->get('/dashboard?range=7');

        $response->assertStatus(200);

        $chartData = $response->viewData('chartData');
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertIsArray($chartData['labels']);

        // Expect the labels to reflect the user's local dates for the snapshots.
        $snapshot1LocalDate = $snapshot1CheckedAtUtc->copy()->setTimezone($userTimezone)->toDateString();
        $snapshot2LocalDate = $snapshot2CheckedAtUtc->copy()->setTimezone($userTimezone)->toDateString();

        $this->assertContains($snapshot1LocalDate, $chartData['labels']);
        $this->assertContains($snapshot2LocalDate, $chartData['labels']);
    }

    public function test_per_check_data_respects_user_timezone(): void
    {
        $user = $this->createUserWithTimezone('America/New_York');

        // Create a snapshot at a specific time
        $snapshot = UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 200,
            'used' => 100,
            'percent_remaining' => 66.67,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => now('UTC')->setTime(4, 0, 0), // 4 AM UTC = 11 PM EST (previous day)
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');
        
        $response->assertStatus(200);
        $perCheckData = $response->viewData('perCheckData');
        
        $this->assertNotNull($perCheckData);
        $this->assertArrayHasKey('timestamps', $perCheckData);
        $this->assertArrayHasKey('used', $perCheckData);
    }

    public function test_per_check_data_includes_nonzero_values(): void
    {
        $user = $this->createUserWithTimezone('America/New_York');

        // Create snapshots with values in the hundreds
        for ($i = 0; $i < 5; $i++) {
            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 500 - (100 + $i * 10),
                'used' => 100 + $i * 10,
                'percent_remaining' => ((500 - (100 + $i * 10)) / 500) * 100,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => now()->subHours(5 - $i),
            ]);
        }

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');
        
        $response->assertStatus(200);
        
        // Check that per-check data exists
        $perCheckData = $response->viewData('perCheckData');
        $this->assertNotNull($perCheckData);
        $this->assertGreaterThan(0, count($perCheckData['used']));
        
        // The minimum value should not be 0, it should be based on actual data
        // (This provides the backend data assumptions for frontend y-axis scaling)
        $minValue = min($perCheckData['used']);
        $this->assertGreaterThan(0, $minValue);
    }

    public function test_can_update_timezone_via_api(): void
    {
        $user = $this->createUserWithTimezone('UTC');

        $response = $this->actingAs($user)->postJson('/dashboard/timezone', [
            'timezone' => 'America/Los_Angeles',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'timezone' => 'America/Los_Angeles',
        ]);

        $this->assertEquals('America/Los_Angeles', $user->fresh()->timezone);
    }

    public function test_timezone_update_validates_timezone(): void
    {
        $user = $this->createUserWithTimezone('UTC');

        $response = $this->actingAs($user)->postJson('/dashboard/timezone', [
            'timezone' => 'Invalid/Timezone',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('UTC', $user->fresh()->timezone);
    }

    public function test_timezone_update_requires_authentication(): void
    {
        $response = $this->postJson('/dashboard/timezone', [
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(401);
    }

    public function test_today_range_shows_only_current_day(): void
    {
        $user = $this->createUserWithTimezone('America/New_York');

        // Create snapshots across multiple days
        $now = \Carbon\Carbon::now('America/New_York');
        
        // Yesterday's snapshots (should NOT be included in "Today" view)
        for ($i = 0; $i < 5; $i++) {
            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 500 - (100 + $i * 10),
                'used' => 100 + $i * 10,
                'percent_remaining' => ((500 - (100 + $i * 10)) / 500) * 100,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => $now->copy()->subDay()->setTime(10 + $i, 0, 0)->setTimezone('UTC'),
            ]);
        }
        
        // Today's snapshots (SHOULD be included in "Today" view)
        for ($i = 0; $i < 5; $i++) {
            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 500 - (200 + $i * 10),
                'used' => 200 + $i * 10,
                'percent_remaining' => ((500 - (200 + $i * 10)) / 500) * 100,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => $now->copy()->setTime(8 + $i, 0, 0)->setTimezone('UTC'),
            ]);
        }

        $this->mock(GitHubCopilotService::class);

        // Request with range=0 (Today)
        $response = $this->actingAs($user)->get('/dashboard?range=0&offset=0');
        
        $response->assertStatus(200);
        $this->assertEquals(0, $response->viewData('chartRange'));
        $this->assertEquals('Today', $response->viewData('chartRangeLabel'));
        
        // Verify only today's data is included
        $perCheckData = $response->viewData('perCheckData');
        $this->assertNotNull($perCheckData);
        // Should have 5 today's snapshots (yesterday's should be excluded)
        $this->assertGreaterThan(0, count($perCheckData['used']));

        // Ensure no timestamps from the prior day are present (in user timezone)
        $timestamps = $perCheckData['timestamps'] ?? [];
        $this->assertNotEmpty($timestamps);

        $todayDate = $now->toDateString();
        $yesterdayDate = $now->copy()->subDay()->toDateString();

        foreach ($timestamps as $timestamp) {
            $timestampDate = \Carbon\Carbon::parse($timestamp)->setTimezone('America/New_York')->toDateString();
            $this->assertEquals($todayDate, $timestampDate, 'All data points should be from today.');
            $this->assertNotEquals($yesterdayDate, $timestampDate, 'No data points from yesterday should be included.');
        }
    }

    public function test_yesterday_range_label(): void
    {
        $user = $this->createUserWithTimezone('America/New_York');
        
        // Create a snapshot so latestSnapshot() doesn't return null
        UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 500,
            'remaining' => 300,
            'used' => 200,
            'percent_remaining' => 60.0,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => now(),
        ]);

        $this->mock(GitHubCopilotService::class);

        // Request with range=0, offset=1 (Yesterday)
        $response = $this->actingAs($user)->get('/dashboard?range=0&offset=1');
        
        $response->assertStatus(200);
        $this->assertEquals('Yesterday', $response->viewData('chartRangeLabel'));
    }

    public function test_today_range_uses_local_midnight_boundary_when_filtering_utc_timestamps(): void
    {
        $user = $this->createUserWithTimezone('America/Los_Angeles');
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00', 'America/Los_Angeles'));

        try {
            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 450,
                'used' => 50,
                'percent_remaining' => 90.0,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => Carbon::parse('2026-02-11 20:00:00', 'America/Los_Angeles')->setTimezone('UTC'),
            ]);

            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 430,
                'used' => 70,
                'percent_remaining' => 86.0,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => Carbon::parse('2026-02-12 00:30:00', 'America/Los_Angeles')->setTimezone('UTC'),
            ]);

            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 410,
                'used' => 90,
                'percent_remaining' => 82.0,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => Carbon::parse('2026-02-12 09:00:00', 'America/Los_Angeles')->setTimezone('UTC'),
            ]);

            $this->mock(GitHubCopilotService::class);

            $response = $this->actingAs($user)->get('/dashboard?range=0&offset=0');
            $response->assertStatus(200);

            $timestamps = $response->viewData('perCheckData')['timestamps'];
            $localDates = array_map(
                fn ($timestamp) => Carbon::parse($timestamp)->setTimezone('America/Los_Angeles')->toDateString(),
                $timestamps
            );

            $this->assertEquals(['2026-02-12', '2026-02-12'], $localDates);
        } finally {
            Carbon::setTestNow();
        }
    }
}
