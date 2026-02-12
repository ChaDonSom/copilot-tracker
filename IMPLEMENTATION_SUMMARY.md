# Dashboard Graph Improvements - Implementation Summary

## Problem Statement

The dashboard graph had two issues:

1. **Time Alignment Issue**: For users in timezones like America/New_York, the '1D' view on 'per-check' mode showed the back half of yesterday and front half of today, with a flat section in the middle for nighttime. This was because the chart used UTC for day boundaries instead of the user's local timezone.

2. **Y-Axis Scaling Issue**: The chart started at 0, but when premium request counts are in the hundreds, this compressed the graph line making it harder to see features and trends.

## Solution Implemented

### 1. User Timezone Support

**Database Changes:**
- Added `timezone` column to `users` table (defaults to 'UTC')
- Migration: `2026_02_12_202322_add_timezone_to_users_table.php`

**Model Changes:**
- Updated `User` model to include `timezone` in fillable fields
- Added `getUserTimezone()` helper method
- Modified `todaySnapshots()` to calculate day boundaries in user's timezone

**Controller Changes:**
- `calculateDateRange()` now accepts user parameter and uses `now($timezone)` instead of `now()`
- `preparePerCheckData()` converts timestamps to user timezone for display and calculations
- `prepareChartDataFromHistory()` groups snapshots by date in user timezone
- `buildChartRangeLabel()` uses user timezone for date formatting

### 2. Dynamic Y-Axis Scaling

**Frontend Changes (dashboard.blade.php):**
- Modified `applyViewToChart()` function to calculate Y-axis minimum dynamically for per-check view
- Formula: `yMin = max(0, floor(minValue - range * 0.1))`
  - Finds minimum value in "Cumulative Used" dataset (blue line)
  - Adds 10% padding below minimum
  - Clamps at 0 (never goes negative)
- Daily view keeps `beginAtZero: true` for consistency with existing behavior

## How It Works

### Timezone Alignment
When a user in America/New_York (EST/EDT, UTC-5/-4) views the dashboard:

1. **Day Boundaries**: Instead of using UTC midnight (8 PM EST / 7 PM EDT), the chart now uses the user's local midnight
2. **Data Grouping**: Snapshots are grouped by date in the user's timezone, not UTC
3. **Time Display**: Per-check chart shows times in user's timezone (e.g., "Feb 12 14:00" instead of "Feb 12 19:00")

### Y-Axis Scaling
For per-check view with values ranging from 200-272:

**Before (beginAtZero: true):**
- Y-axis: 0 to 300
- Data occupies only 24% of chart height (72 / 300)
- Hard to see trend details

**After (dynamic minimum):**
- Minimum value: 200
- Range: 72
- Padding: 7.2 (10% of range)
- Y-axis: ~193 to 272
- Data occupies ~91% of chart height
- Much easier to see trends and features

## User Configuration

Users can set their timezone using Laravel Tinker or direct database update:

```php
// Using Tinker
$user = App\Models\User::where('github_username', 'username')->first();
$user->timezone = 'America/New_York';
$user->save();
```

See README.md for full documentation.

## Testing

Created comprehensive test suite (`DashboardTimezoneTest.php`) covering:
- Default timezone behavior (UTC)
- Custom timezone settings
- Date range calculations with timezones
- Per-check data timezone conversions
- Y-axis dynamic scaling

All tests pass, including existing tests (18 total tests, 51 assertions).

## Files Modified

1. `database/migrations/2026_02_12_202322_add_timezone_to_users_table.php` (new)
2. `app/Models/User.php`
3. `app/Http/Controllers/DashboardController.php`
4. `resources/views/dashboard.blade.php`
5. `tests/Feature/DashboardTimezoneTest.php` (new)
6. `README.md`
7. `.gitignore`

## Backward Compatibility

- Existing users default to UTC timezone (maintains current behavior)
- Daily view chart keeps beginAtZero behavior
- No breaking changes to API or existing functionality

## Future Enhancements

- Settings UI for users to change their timezone through the web interface
- Automatic timezone detection based on browser/system settings
- Per-check view could also apply dynamic Y-axis to recommendation line

## Demo Data

A demo data seeder (`seed-demo-data.php`) was created to test the implementation but is not committed to the repository.
