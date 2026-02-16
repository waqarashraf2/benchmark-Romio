<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIssue extends Model
{
    protected $fillable = [
        'order_id',
        'order_review_id',
        'severity',
        'description',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(OrderReview::class, 'order_review_id');
    }
}
