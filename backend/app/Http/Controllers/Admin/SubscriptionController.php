<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $subscriptions = Subscription::with(['account', 'plan'])
            ->orderByDesc('id')
            ->paginate(15);

        return SubscriptionResource::collection($subscriptions);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        $subscription->load(['account', 'plan']);

        return new SubscriptionResource($subscription);
    }

    public function update(Request $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validate([
            'plan_id' => ['sometimes', 'required', 'exists:plans,id'],
            'status' => ['sometimes', 'required', 'in:active,past_due,canceled'],
        ]);

        $subscription->update($data);
        $subscription->load(['account', 'plan']);

        return new SubscriptionResource($subscription);
    }
}
