<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
        use HasApiTokens;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'active',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

public function role() {
    return $this->belongsTo(Role::class);
}


    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(OrderReview::class, 'reviewer_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(OrderChecklist::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }
}
