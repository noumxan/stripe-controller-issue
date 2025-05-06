<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Practice;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MigrateOldSubscriptions extends Command
{
    /**
     * Command signature to run this migration.
     */
    protected $signature = 'subscriptions:migrate-old';

    /**
     * Description of what this command does.
     */
    protected $description = 'Migrate existing practices to new subscription_items table for compatibility with Stripe and caching updates.';

    /**
     * This command migrates old subscription info into the new subscription_items structure.
     *
     * Context from issue:
     * - Every Practice can have multiple subscriptions (APP_ACCESS and SMS).
     * - Practices must have one active APP_ACCESS subscription (main subscription based on practitioners).
     * - Pricing logic: 30€ base price includes 1 practitioner, each extra user costs 10€.
     * - We need to ensure it works for current subscribers without breaking old setups.
     */
    public function handle()
    {
        Log::info('Starting migration of old subscriptions into subscription_items.');

        DB::beginTransaction();

        try {
            // Fetching all practices that already have a Stripe customer ID.
            $practices = Practice::whereNotNull('stripe_id')->get();

            foreach ($practices as $practice) {
                // Assuming all practices have a 30€ subscription with 1 included user.
                $basePrice = 30; // Hardcoded, matches old subscription pricing
                $includedPractitioners = 1; // 1 included practitioner in base plan
                $extraUserPrice = 10; // Each extra practitioner costs 10€

                // Finding the SubscriptionPlan record for APP_ACCESS type.
                $plan = SubscriptionPlan::where('type', 'APP_ACCESS')->first();
                if (!$plan) {
                    Log::error('Could not find APP_ACCESS subscription plan. Migration cannot continue.');
                    DB::rollBack();
                    return Command::FAILURE;
                }

                // Check if the practice already has an existing subscription item.
                $alreadyMigrated = SubscriptionItem::where('practice_id', $practice->id)
                    ->where('type', 'APP_ACCESS')
                    ->exists();

                if ($alreadyMigrated) {
                    Log::info("Practice ID {$practice->id} already has a subscription_item. Skipping...");
                    continue;
                }

                // Counting how many active practitioners the practice currently has.
                $currentPractitionerCount = $practice->practitioners()->count();

                // Creating a new SubscriptionItem for this practice based on current data.
                SubscriptionItem::create([
                    'practice_id' => $practice->id,
                    'subscription_plan_id' => $plan->id,
                    'stripe_subscription_id' => null, // Will be updated later through webhooks
                    'practitioner_limit' => $includedPractitioners,
                    'current_practitioners' => $currentPractitionerCount,
                    'extra_user_price' => $extraUserPrice,
                    'type' => 'APP_ACCESS',
                    'starts_at' => now(),
                    'ends_at' => null,
                    'is_active' => true,
                ]);

                Log::info("Successfully migrated Practice ID {$practice->id} into subscription_items.");
            }

            DB::commit();
            Log::info('Migration finished successfully.');

            $this->info('✅ All old subscriptions migrated successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Migration error: ' . $e->getMessage());
            $this->error('❌ Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
