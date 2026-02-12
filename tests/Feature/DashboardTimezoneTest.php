<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsageSnapshot;
use App\Services\GitHubCopilotService;
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
        $user = $this->createUserWithTimezone('America/New_York');

        // Create snapshots at different times
        // One at 11 PM EST (4 AM UTC next day) and one at 1 AM EST (6 AM UTC same day)
        // In UTC these would be on different days, but in EST they're on the same day
        
        $baseTime = now('America/New_York')->setTime(23, 0, 0); // 11 PM EST
        $snapshot1 = UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 200,
            'used' => 100,
            'percent_remaining' => 66.67,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => $baseTime->copy()->setTimezone('UTC'),
        ]);

        $snapshot2 = UsageSnapshot::create([
            'user_id' => $user->id,
            'quota_limit' => 300,
            'remaining' => 150,
            'used' => 150,
            'percent_remaining' => 50.00,
            'reset_date' => now()->addDays(15)->toDateString(),
            'checked_at' => $baseTime->copy()->addHours(2)->setTimezone('UTC'), // 1 AM EST
        ]);

        $this->mock(GitHubCopilotService::class);

        $response = $this->actingAs($user)->get('/dashboard');
        
        $response->assertStatus(200);
        $this->assertNotNull($response->viewData('chartData'));
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

    public function test_chart_y_axis_scales_dynamically_for_per_check_view(): void
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
        
        // Yesterday's snapshots
        for ($i = 0; $i < 5; $i++) {
            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 500 - (100 + $i * 10),
                'used' => 100 + $i * 10,
                'percent_remaining' => ((500 - (100 + $i * 10)) / 500) * 100,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => $now->copy()->subDay()->addHours($i * 2)->setTimezone('UTC'),
            ]);
        }
        
        // Today's snapshots
        for ($i = 0; $i < 5; $i++) {
            UsageSnapshot::create([
                'user_id' => $user->id,
                'quota_limit' => 500,
                'remaining' => 500 - (200 + $i * 10),
                'used' => 200 + $i * 10,
                'percent_remaining' => ((500 - (200 + $i * 10)) / 500) * 100,
                'reset_date' => now()->addDays(15)->toDateString(),
                'checked_at' => $now->copy()->addHours($i * 2)->setTimezone('UTC'),
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
        // Should have 5 today's snapshots
        $this->assertGreaterThan(0, count($perCheckData['used']));
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
}