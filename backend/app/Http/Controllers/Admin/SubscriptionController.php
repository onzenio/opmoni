<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $subscriptions = Subscription::with(['account', 'plan'])
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($subscriptions);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load(['account', 'plan']);

        return response()->json($subscription);
    }

    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['sometimes', 'required', 'exists:plans,id'],
            'status' => ['sometimes', 'required', 'in:active,past_due,canceled'],
        ]);

        $subscription->update($data);
        $subscription->load(['account', 'plan']);

        return response()->json($subscription);
    }
}
