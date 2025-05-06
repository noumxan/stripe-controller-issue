<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Practice;
use App\Services\SubscriptionCacheService;

class SubscriptionsSyncCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate and cache subscription info for all practices';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🔁 Syncing subscription cache for all practices...');

        $service = new SubscriptionCacheService();

        Practice::with('subscriptionItems.subscriptionPlan')
            ->get()
            ->each(function ($practice) use ($service) {
                $service->cacheSubscriptionInfo($practice);
                $this->line("✅ Cached subscription for Practice #{$practice->id}");
            });

        $this->info('🎉 Done! Subscription info has been cached.');
    }
}
