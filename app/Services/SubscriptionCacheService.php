<?php

namespace App\Services;

use App\Models\Practice;
use Illuminate\Support\Facades\Cache;

class SubscriptionCacheService
{
    public function cacheSubscriptionInfo(Practice $practice): void
    {
        $item = $practice->subscriptionItems()->where('type', 'APP_ACCESS')->first();

        if (!$item || !$item->subscriptionPlan) return;

        $plan = $item->subscriptionPlan;
        $included = $plan->included_practitioners ?? 1;
        $extraUsers = max(0, $item->practitioner_count - $included);
        $extraPrice = $plan->extra_user_price ?? 10.00;
        $extraCost = $extraUsers * $extraPrice;
        $totalCost = $plan->base_price + $extraCost;

        Cache::put("practice:{$practice->id}:subscription", [
            'subscription_item_id' => $item->id,
            'subscription_type' => $item->type,
            'base_price' => $plan->base_price,
            'included_practitioners' => $included,
            'practitioner_count' => $item->practitioner_count,
            'extra_user_price' => $extraPrice,
            'extra_cost' => $extraCost,
            'total_cost' => $totalCost,
        ], now()->addHours(2));
    }

    public function getCachedSubscriptionInfo(int $practiceId): ?array
    {
        return Cache::get("practice:$practiceId:subscription");
    }

    public function clearCache(int $practiceId): void
    {
        Cache::forget("practice:$practiceId:subscription");
    }
}
