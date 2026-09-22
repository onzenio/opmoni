<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::with('subscription.plan')
            ->withCount('members')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Account::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,suspended'],
            'settings' => ['nullable', 'array'],
        ]);

        $account = Account::create($data);
        $account->load('subscription.plan');
        $account->loadCount('members');

        return response()->json($account, 201);
    }

    public function show(Account $account): JsonResponse
    {
        Gate::authorize('view', $account);

        $account->load('subscription.plan');
        $account->loadCount('members');

        return response()->json($account);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        Gate::authorize('update', $account);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:active,suspended'],
            'settings' => ['nullable', 'array'],
        ]);

        $account->update($data);
        $account->load('subscription.plan');
        $account->loadCount('members');

        return response()->json($account);
    }
}
