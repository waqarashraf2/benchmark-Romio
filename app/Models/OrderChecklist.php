<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChecklist extends Model
{
    protected $fillable = [
        'order_id',
        'checklist_id',
        'user_id',
        'checked',
        'checked_at',
    ];

    protected $casts = [
        'checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
