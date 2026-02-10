# Copilot Tracker - Laravel App

A Laravel application for centrally tracking GitHub Copilot premium request usage across multiple machines with **web dashboard** and API access.

## Features

- 🌐 **Web Dashboard with OAuth** - Login with GitHub to view your usage graphs
- 📊 **Usage Visualization** - Interactive charts showing usage trends over time
- 🔐 **GitHub Token Authentication** - API access using your GitHub token
- 📈 **Cross-Machine Tracking** - See your usage from any machine
- ⏰ **Hourly Checks** - Scheduled jobs automatically check usage every hour
- 🚀 **API Endpoints** - RESTful API for the bash script integration
- 🔒 **Secure Token Storage** - Tokens are encrypted at rest

## Requirements

- PHP 8.2+
- Composer
- SQLite (for local development)
- **PostgreSQL (required for Laravel Cloud production)**

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

Visit `http://localhost:8000` and click "Login with GitHub" to access the dashboard.

### GitHub OAuth Setup (Required for Web Dashboard)

To enable GitHub OAuth login for the web dashboard:

1. **Create a GitHub OAuth App:**
   - Go to https://github.com/settings/developers
   - Click "New OAuth App"
   - Fill in the form:
     - **Application name:** Copilot Usage Tracker
     - **Homepage URL:** `http://localhost:8000` (or your production URL)
     - **Authorization callback URL:** `http://localhost:8000/login/github/callback`
   - Click "Register application"
   - Copy your **Client ID** and **Client Secret**

2. **Configure Environment Variables:**
   ```env
   GITHUB_CLIENT_ID=your_client_id_here
   GITHUB_CLIENT_SECRET=your_client_secret_here
   GITHUB_REDIRECT_URL=http://localhost:8000/login/github/callback
   ```

3. **Restart your Laravel server:**
   ```bash
   php artisan serve
   ```

Now you can login via the web interface and view your usage graphs!

### Laravel Cloud Deployment

**⚠️ Important:** Laravel Cloud requires PostgreSQL (SQLite is not supported).

1. **Create PostgreSQL Database:**
   - Go to Laravel Cloud dashboard → "Databases"
   - Create new PostgreSQL database
   - Note: Free tier costs $0 with no usage
   - Copy the connection credentials

2. **Configure Environment Variables:**
   ```env
   APP_ENV=production
   APP_KEY=base64:YOUR_GENERATED_KEY
   APP_DEBUG=false
   
   DB_CONNECTION=pgsql
   DB_HOST=your-host.laravel.cloud
   DB_PORT=5432
   DB_DATABASE=your-database
   DB_USERNAME=your-username
   DB_PASSWORD=your-password
   ```

3. **Push to Repository:**
   ```bash
   git push origin master
   ```

4. **Run Migrations** (in Laravel Cloud console):
   ```bash
   php artisan migrate --force
   ```

5. **Enable Scheduler** (optional, for automatic checks):
   - Enable in Laravel Cloud settings
   - Runs `copilot:check-usage` every hour

Deployment takes approximately 40-60 seconds.

**Troubleshooting:** See [../DEBUGGING_GUIDE.md](../DEBUGGING_GUIDE.md) for common issues.

## Using the Web Dashboard

### Access

1. Navigate to your deployed Laravel app URL
2. Click "Login with GitHub" 
3. Authorize the application
4. View your Copilot usage dashboard with interactive graphs

### Dashboard Features

- **Current Usage Stats** - See your quota limit, remaining requests, and usage percentage
- **Usage Trend Graph** - 30-day historical view showing:
  - Daily requests used
  - Remaining requests over time
- **Visual Warnings** - Color-coded progress bars warn when running low on quota
- **Auto-Refresh** - Click refresh to get latest data from GitHub

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
