<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'external_order_id',
        'order_number',
        'order_id',
        'client_id',
        'project_id',
        'address',
        'ausDatein',
        'property',
        'batch',
        'instruction',
        'status',
        'priority',
        'source',
        'd_live_qa',
        'c_live_qa',
        'qa_live_qa',
        'due_at',
        'due_in',
        'assigned_at',
        'drawer_started_at',
        'drawer_completed_at',
        'checker_started_at',
        'checker_completed_at',
        'qa_started_at',
        'qa_completed_at',
        'created_from_api_at',
        'created_at',
    ];

    protected $casts = [
        'd_live_qa' => 'boolean',
        'c_live_qa' => 'boolean',
        'qa_live_qa' => 'boolean',
        'assigned_at' => 'datetime',
        'drawer_started_at' => 'datetime',
        'drawer_completed_at' => 'datetime',
        'checker_started_at' => 'datetime',
        'checker_completed_at' => 'datetime',
        'qa_started_at' => 'datetime',
        'qa_completed_at' => 'datetime',
        'due_at' => 'datetime',
        'created_from_api_at' => 'datetime',
    ];

    /* STATUS CONSTANTS */
    const STATUS_PENDING = 'pending';
    const STATUS_ASSIGNED = 'assigned_to_drawer';
    const STATUS_DRAWER_DONE = 'drawer_completed';
    const STATUS_CHECKER_REVIEW = 'checker_review';
    const STATUS_CHECKER_DONE = 'checker_completed';
    const STATUS_QA_REVIEW = 'qa_review';
    const STATUS_QA_DONE = 'qa_completed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    /* RELATIONSHIPS */

    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class);
    }

    public function currentAssignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class)->where('is_current', true);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(OrderReview::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OrderIssue::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(OrderChecklist::class);
    }

    /* BUSINESS HELPERS */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function moveTo(string $newStatus, ?int $userId = null): void
    {
        $oldStatus = $this->status;

        $this->update(['status' => $newStatus]);

        $this->statusLogs()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' => $userId,
        ]);
    }
}