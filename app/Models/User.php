<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'github_username',
        'github_token',
        'github_oauth_token',
        'timezone',
        'copilot_plan',
        'quota_limit',
        'quota_reset_date',
        'last_checked_at',
    ];

    protected $hidden = [
        'github_token',
        'github_oauth_token',
    ];

    protected function casts(): array
    {
        return [
            'quota_reset_date' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function setGithubTokenAttribute(?string $value): void
    {
        $this->attributes['github_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getGithubTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Get the active GitHub token, preferring OAuth token over PAT.
     */
    public function getActiveGithubToken(): ?string
    {
        return $this->github_oauth_token ?? $this->github_token;
    }

    public function setGithubOauthTokenAttribute(?string $value): void
    {
        $this->attributes['github_oauth_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getGithubOauthTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function usageSnapshots(): HasMany
    {
        return $this->hasMany(UsageSnapshot::class);
    }

    public function latestSnapshot(): ?UsageSnapshot
    {
        return $this->usageSnapshots()->latest('checked_at')->first();
    }

    public function todaySnapshots()
    {
        $timezone = $this->getUserTimezone();
        $now = now($timezone);
        $startOfDay = $now->copy()->startOfDay();
        $startOfNextDay = $now->copy()->addDay()->startOfDay();
        
        return $this->usageSnapshots()
            ->where('checked_at', '>=', $startOfDay->setTimezone('UTC'))
            ->where('checked_at', '<', $startOfNextDay->setTimezone('UTC'))
            ->orderBy('checked_at');
    }

    /**
     * Get the user's timezone, defaulting to UTC.
     */
    public function getUserTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }
}
