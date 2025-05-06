<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Models\Practice;

class SubscriptionAdminController extends Controller
{
    public function index()
    {
        $practices = Practice::with('subscriptionItems')->get();

        $subscriptions = [];

        foreach ($practices as $practice) {
            $cache = Cache::get("practice:{$practice->id}:subscription");

            if ($cache) {
                $subscriptions[] = [
                    'practice' => $practice,
                    'data' => $cache,
                ];
            }
        }

        $totalRevenue = collect($subscriptions)->sum(fn ($s) => $s['data']['total_cost']);

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'totalRevenue' => $totalRevenue,
        ]);
    }
}
