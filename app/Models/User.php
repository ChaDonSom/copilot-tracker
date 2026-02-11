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

    public function setGithubTokenAttribute(string $value): void
    {
        $this->attributes['github_token'] = Crypt::encryptString($value);
    }

    public function getGithubTokenAttribute(string $value): string
    {
        return Crypt::decryptString($value);
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
        return $this->usageSnapshots()
            ->whereDate('checked_at', now()->toDateString())
            ->orderBy('checked_at');
    }
}
