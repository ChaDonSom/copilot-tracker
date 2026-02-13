# Dashboard Graph Improvements - Implementation Summary

## Problem Statement

The dashboard graph had three issues:

1. **Time Alignment Issue**: For users in timezones like America/New_York, the '1D' view on 'per-check' mode showed the back half of yesterday and front half of today, with a flat section in the middle for nighttime. This was because the chart used UTC for day boundaries instead of the user's local timezone.

2. **Y-Axis Scaling Issue**: The chart started at 0, but when premium request counts are in the hundreds, this compressed the graph line making it harder to see features and trends.

3. **"Today" vs "Last 24 Hours"**: The original "1D" button showed "last 24 hours" which spans two calendar days. Users wanted a way to see only the current day's data aligned with their timezone.

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
- Special handling for range=0 to show "today only" (midnight to now in user's timezone)
- `preparePerCheckData()` converts timestamps to user timezone for display and calculations
- `prepareChartDataFromHistory()` groups snapshots by date in user timezone
- `buildChartRangeLabel()` uses user timezone for date formatting with "Today"/"Yesterday" labels
- Added `updateTimezone()` endpoint to allow timezone updates via POST request

**Frontend Changes:**
- Added automatic timezone detection using JavaScript's `Intl.DateTimeFormat().resolvedOptions().timeZone`
- Automatically sends detected timezone to server on dashboard load
- Reloads page after timezone update to apply new settings
- Added CSRF token meta tag for secure POST requests

### 2. Dynamic Y-Axis Scaling

**Frontend Changes (dashboard.blade.php):**
- Modified `applyViewToChart()` function to calculate Y-axis minimum dynamically for per-check view
- Formula: `yMin = max(0, floor(minValue - range * 0.1))`
  - Finds minimum value in "Cumulative Used" dataset (blue line)
  - Adds 10% padding below minimum
  - Clamps at 0 (never goes negative)
- Daily view keeps `beginAtZero: true` for consistency with existing behavior

### 3. "Today" Range Option

**UI Changes:**
- Added "Today" button to range selector (shows as first option)
- Keeps existing "1D", "7D", "30D" buttons
- "Today" button internally uses `range=0` to indicate current calendar day only

**Backend Changes:**
- Modified `ALLOWED_RANGES` to include 0
- Updated `calculateDateRange()` to handle range=0 as special case:
  - Returns `[startOfDay, endOfDay]` for the current day in user's timezone
  - With offset: offset=1 shows yesterday, offset=2 shows day before, etc.
- Updated `buildChartRangeLabel()` to show "Today", "Yesterday", or specific date

**Frontend Changes:**
- Updated `updateRangeLabel()` JavaScript function to handle range=0
- Shows contextual labels: "Today" (offset=0), "Yesterday" (offset=1), or date (offset>1)

## How It Works

### Timezone Alignment
When a user in America/New_York (EST/EDT, UTC-5/-4) views the dashboard:

1. **Day Boundaries**: Instead of using UTC midnight (8 PM EST / 7 PM EDT), the chart now uses the user's local midnight
2. **Data Grouping**: Snapshots are grouped by date in the user's timezone, not UTC
3. **Time Display**: Per-check chart shows times in user's timezone (e.g., "Feb 12 14:00" instead of "Feb 12 19:00")

### "Today" vs "1D" Range
- **"Today" (range=0)**: Shows data from user's local midnight (00:00) until now
  - Clicking ◀ shows yesterday's data (same day boundary logic)
  - Clicking ▶ returns to today (when viewing past days)
- **"1D" (range=1)**: Shows last 24 hours from current time
  - Example: If it's 3 PM on Feb 12, shows data from 3 PM Feb 11 to 3 PM Feb 12
  - May span across two calendar days

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

**Automatic Detection (Recommended):**
The dashboard automatically detects the user's timezone from their browser using JavaScript:
- Uses `Intl.DateTimeFormat().resolvedOptions().timeZone` to get browser timezone
- Automatically sends to server on dashboard load
- Only updates if timezone differs from current setting
- Reloads page to apply new timezone

**Manual Override (If Needed):**
Users can manually set a different timezone using Laravel Tinker:

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
- Timezone update API endpoint
- Timezone validation
- Authentication requirements
- "Today" range functionality
- "Yesterday" label display

All tests pass, including existing tests (23 total tests, 64 assertions).

## Files Modified

1. `database/migrations/2026_02_12_202322_add_timezone_to_users_table.php` (new)
2. `app/Models/User.php`
3. `app/Http/Controllers/DashboardController.php`
4. `resources/views/dashboard.blade.php`
5. `tests/Feature/DashboardTimezoneTest.php` (updated)
6. `routes/web.php` (updated)
7. `README.md`
8. `.gitignore`
9. `IMPLEMENTATION_SUMMARY.md` (updated)

## Backward Compatibility

- Existing users default to UTC timezone (maintains current behavior)
- Automatic timezone detection implemented - users no longer need manual configuration
- Daily view chart keeps beginAtZero behavior
- No breaking changes to API or existing functionality

## Future Enhancements

- Settings UI for users to override automatically detected timezone
- Per-check view could also apply dynamic Y-axis to recommendation line
- Timezone preference could be persisted across multiple devices
