<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Practice;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SubscriptionCancelledNotification;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook events.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request)
    {
        // Log incoming request for debugging
        Log::info('Stripe webhook received.');

        try {
            // Process the event
            $event = $this->verifyWebhook($request);

            // Log event data for debugging purposes
            Log::info('Stripe event data: ', (array) $event);

            // Handle the event based on the type
            switch ($event->type) {
                case 'invoice.payment_failed':
                    // Handle payment failure
                    $this->handlePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    // Handle subscription update
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    // Handle subscription cancellation
                    $this->handleSubscriptionCancelled($event->data->object);
                    break;

                default:
                    Log::info("Unhandled event type: {$event->type}");
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
     *
     * @param Request $request
     * @return \Stripe\Event
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
     *
     * @param object $data
     */
    protected function handlePaymentFailed($data)
    {
        $practice = Practice::where('stripe_id', $data->customer)->first();
        if (!$practice) {
            Log::warning("Practice not found for customer ID: {$data->customer}");
            return;
        }

        Log::warning("Payment failed for practice ID {$practice->id}");

        // Send notification to admin
        Notification::route('mail', 'admin@example.com')
            ->notify(new \App\Notifications\Admin\StripePaymentFailedNotification($practice));
    }

    /**
     * Handle subscription update event.
     *
     * @param object $data
     */
    protected function handleSubscriptionUpdated($data)
    {
        $practice = Practice::where('stripe_id', $data->customer)->first();
        if (!$practice) {
            Log::warning("Practice not found for customer ID: {$data->customer}");
            return;
        }

        Cache::put("subscription_{$practice->id}", $data, now()->addMinutes(30));

        Log::info("Subscription updated and cached for practice ID {$practice->id}");
    }

    /**
     * Handle subscription cancellation event.
     *
     * @param object $data
     */
    protected function handleSubscriptionCancelled($data)
    {
        $practice = Practice::where('stripe_id', $data->customer)->first();
        if (!$practice) {
            Log::warning("Practice not found for customer ID: {$data->customer}");
            return;
        }

        Cache::forget("subscription_{$practice->id}");

        if ($mainPractitioner = $practice->practitioners->first()) {
            $mainPractitioner->notify(new SubscriptionCancelledNotification($practice));
        }

        Log::warning("Subscription cancelled for practice ID {$practice->id}");
    }
}
