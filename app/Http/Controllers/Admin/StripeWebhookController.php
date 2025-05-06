<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\Practice;
use App\Models\SubscriptionItem;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\Admin\StripePaymentFailedNotification;
use App\Services\SubscriptionCacheService;
use Stripe\Webhook;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook events.
     */
    public function handle(Request $request)
    {
        Log::info('Stripe webhook received.');

        try {
            $event = $this->verifyWebhook($request);
            Log::info('Stripe event data: ', (array) $event);

            switch ($event->type) {
                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionCancelled($event->data->object);
                    break;

                default:
                    Log::info("Unhandled Stripe event: {$event->type}");
                    break;
            }

            return response('Webhook received', 200);
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook: ' . $e->getMessage());
            return response('Webhook error', 400);
        }
    }

    /**
     * Verify the webhook signature and return the event.
     */
    private function verifyWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        return Webhook::constructEvent($payload, $signature, $endpointSecret);
    }

    /**
     * Handle payment failure event.
     */
    protected function handlePaymentFailed($data)
    {
        $practice = Practice::where('stripe_id', $data->customer)->first();

        if (!$practice) {
            Log::warning("Practice not found for customer ID: {$data->customer}");
            return;
        }

        Log::warning("Payment failed for practice ID {$practice->id}");

        // Notify admin
        Notification::route('mail', config('mail.admin_email', 'admin@example.com'))
            ->notify(new StripePaymentFailedNotification($practice));

        // Optionally set grace period if using a grace_until column
        $subscriptionItem = $practice->subscriptionItems()
            ->where('stripe_subscription_id', $data->subscription)
            ->first();

        if ($subscriptionItem) {
            $subscriptionItem->update([
                'grace_until' => now()->addDays(5),
            ]);
        }
    }

    /**
     * Handle subscription update event.
     */
    protected function handleSubscriptionUpdated($data)
    {
        $practice = Practice::where('stripe_id', $data->customer)->first();
        if (!$practice) {
            Log::warning("Practice not found for customer ID: {$data->customer}");
            return;
        }

        $subscriptionItem = $practice->subscriptionItems()
            ->where('stripe_subscription_id', $data->id)
            ->first();

        if (!$subscriptionItem) {
            Log::warning("SubscriptionItem not found for subscription ID: {$data->id}");
            return;
        }

        $subscriptionItem->update([
            'starts_at' => $data->current_period_start ? Carbon::createFromTimestamp($data->current_period_start) : null,
            'ends_at' => $data->current_period_end ? Carbon::createFromTimestamp($data->current_period_end) : null,
            'is_active' => $data->status === 'active',
        ]);

        // Refresh cache using service
        $service = new SubscriptionCacheService();
        $service->cacheSubscriptionInfo($practice);

        Log::info("Subscription updated and cache refreshed for practice ID {$practice->id}");
    }

    /**
     * Handle subscription cancellation event.
     */
    protected function handleSubscriptionCancelled($data)
    {
        $practice = Practice::where('stripe_id', $data->customer)->first();
        if (!$practice) {
            Log::warning("Practice not found for customer ID: {$data->customer}");
            return;
        }

        $subscriptionItem = $practice->subscriptionItems()
            ->where('stripe_subscription_id', $data->id)
            ->first();

        if ($subscriptionItem) {
            $subscriptionItem->update([
                'is_active' => false,
                'ends_at' => $data->canceled_at ? Carbon::createFromTimestamp($data->canceled_at) : now(),
            ]);
        }

        // Clear and regenerate cache (if desired)
        $service = new SubscriptionCacheService();
        $service->clearCache($practice->id);

        if ($mainPractitioner = $practice->practitioners->first()) {
            $mainPractitioner->notify(new SubscriptionCancelledNotification($practice));
        }

        Log::warning("Subscription cancelled and practitioner notified for practice ID {$practice->id}");
    }
}
