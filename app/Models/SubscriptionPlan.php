<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'stripe_plan_id',
        'description',
        'base_price',
        'type',
        'included_practitioners',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'base_price' => 'decimal:2',
    ];

    /**
     * Get all subscription items based on this plan.
     */
    public function subscriptionItems(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }
}
