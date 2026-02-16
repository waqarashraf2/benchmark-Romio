<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReview extends Model
{
    protected $fillable = [
        'order_id',
        'reviewer_id',
        'role_id',
        'approved',
        'comment',
        'reviewed_at',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OrderIssue::class);
    }
}
