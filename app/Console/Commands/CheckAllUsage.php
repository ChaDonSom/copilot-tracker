<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GitHubCopilotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAllUsage extends Command
{
    protected $signature = 'copilot:check-usage 
                            {--user= : Check a specific user by username}
                            {--dry-run : Show what would be checked without actually checking}';

    protected $description = 'Check Copilot premium usage for all registered users';

    public function __construct(
        private GitHubCopilotService $githubService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $username = $this->option('user');
        $dryRun = $this->option('dry-run');

        $query = User::query();
        
        if ($username) {
            $query->where('github_username', $username);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No users found to check.');
            return self::SUCCESS;
        }

        $this->info("Checking usage for {$users->count()} user(s)...");

        $successCount = 0;
        $failCount = 0;
        $rateLimitHit = false;

        foreach ($users as $user) {
            if ($dryRun) {
                $this->line("  Would check: {$user->github_username}");
                continue;
            }

            $this->line("  Checking: {$user->github_username}...");

            try {
                $snapshot = $this->githubService->checkAndStoreUsage($user);

                if ($snapshot) {
                    $this->info("    ✓ {$snapshot->remaining}/{$snapshot->quota_limit} remaining");
                    $successCount++;
                } else {
                    $this->warn("    - Skipped (unlimited quota or no data)");
                }
            } catch (\Exception $e) {
                $this->error("    ✗ Error: {$e->getMessage()}");
                $failCount++;

                // Check for rate limiting
                if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), '403')) {
                    $rateLimitHit = true;
                    $this->error("Rate limit detected. Stopping further checks.");
                    Log::warning('GitHub API rate limit hit during scheduled check', [
                        'checked_users' => $successCount,
                        'remaining_users' => $users->count() - $successCount - $failCount,
                    ]);
                    break;
                }

                // Small delay between requests to be respectful
                usleep(100000); // 100ms
            }
        }

        if ($dryRun) {
            $this->info("Dry run complete. {$users->count()} user(s) would be checked.");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Complete: {$successCount} successful, {$failCount} failed.");

        if ($rateLimitHit) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
