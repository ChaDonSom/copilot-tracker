<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quota_limit',
        'remaining',
        'used',
        'percent_remaining',
        'reset_date',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'reset_date' => 'date',
            'checked_at' => 'datetime',
            'percent_remaining' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
