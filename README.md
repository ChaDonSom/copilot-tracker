# Copilot Tracker - Laravel App

A Laravel application for centrally tracking GitHub Copilot premium request usage across multiple machines.

## Features

- 🔐 **GitHub Token Authentication** - Uses your GitHub token to identify you
- 📊 **Cross-Machine Tracking** - See your usage from any machine
- ⏰ **Hourly Checks** - Scheduled jobs automatically check usage every hour
- 🚀 **API Endpoints** - RESTful API for the bash script integration
- 🔒 **Secure Token Storage** - Tokens are encrypted at rest

## Requirements

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL

## Installation

### Local Development

```bash
cd copilot-tracker

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Start the development server
php artisan serve
```

### Laravel Cloud Deployment

1. Push to your repository
2. Connect to Laravel Cloud
3. Configure environment variables (APP_KEY, DB settings)
4. Enable the scheduler in Laravel Cloud settings
5. Deploy!

The scheduler is configured to run `copilot:check-usage` every hour automatically.

## API Endpoints

All API endpoints require a GitHub token as Bearer authentication:

```bash
curl -H "Authorization: Bearer YOUR_GITHUB_TOKEN" https://your-app.laravel.cloud/api/usage
```

### Health Check

```
GET /health
```

Returns server status. Used by the bash script to check availability.

```json
{
  "status": "ok",
  "timestamp": "2026-02-10T16:00:00.000Z"
}
```

### Get Usage (Cached)

```
GET /api/usage
```

Returns the latest cached usage data.

```json
{
  "username": "your-username",
  "copilot_plan": "individual_pro",
  "usage": {
    "quota_limit": 1500,
    "remaining": 1054,
    "used": 446,
    "percent_remaining": 70.28,
    "reset_date": "2026-03-01"
  },
  "checked_at": "2026-02-10T15:00:00.000Z",
  "cached": true
}
```

### Force Refresh

```
POST /api/usage/refresh
```

Forces a fresh check from the GitHub API.

### Today's Usage

```
GET /api/usage/today
```

Returns usage delta for the current day.

```json
{
  "username": "your-username",
  "date": "2026-02-10",
  "used_today": 25,
  "current": {
    "remaining": 1054,
    "used": 446,
    "quota_limit": 1500,
    "percent_remaining": 70.28
  },
  "snapshots_today": 8,
  "first_check": "2026-02-10T09:00:00.000Z",
  "last_check": "2026-02-10T16:00:00.000Z"
}
```

### Usage History

```
GET /api/usage/history?days=7&limit=50
```

Returns historical usage snapshots.

## Using with the Bash Script

Set the server URL either via environment variable or command flag:

```bash
# Environment variable
export COPILOT_TRACKER_URL="https://your-app.laravel.cloud"
./check-copilot-usage.sh

# Or via flag
./check-copilot-usage.sh --server https://your-app.laravel.cloud

# Force a fresh check from GitHub
./check-copilot-usage.sh --force-refresh

# Force local check (ignore server)
./check-copilot-usage.sh --local
```

## Artisan Commands

### Check All Users

```bash
# Check all registered users
php artisan copilot:check-usage

# Check a specific user
php artisan copilot:check-usage --user=username

# Dry run (show what would be checked)
php artisan copilot:check-usage --dry-run
```

## Security

- GitHub tokens are encrypted using Laravel's `Crypt::encryptString()`
- Tokens are never exposed in API responses
- Authentication is validated against GitHub's API on each request
- HTTPS should be enforced in production

## Rate Limiting

The app is designed to be respectful of GitHub's API rate limits:

- Default check interval: 1 hour
- Each user check = 2 API calls (validate + fetch usage)
- If rate limited, the scheduler backs off and logs the issue

## Database Schema

### Users Table
- `github_username` - Unique GitHub username
- `github_token` - Encrypted GitHub token
- `copilot_plan` - Copilot subscription plan
- `quota_limit` - Monthly quota limit
- `quota_reset_date` - When quota resets
- `last_checked_at` - Last successful check

### Usage Snapshots Table
- `user_id` - Reference to user
- `quota_limit` - Limit at time of check
- `remaining` - Requests remaining
- `used` - Requests used
- `percent_remaining` - Percentage remaining
- `reset_date` - Quota reset date
- `checked_at` - When this snapshot was taken

## License

MIT
