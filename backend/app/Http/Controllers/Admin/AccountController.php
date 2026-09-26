<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function index(IndexAccountRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $accounts = Account::with('subscription.plan')
            ->withCount('members')
            ->search($filters['q'] ?? null)
            ->withStatus($filters['status'] ?? null)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return AccountResource::collection($accounts);
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

        return (new AccountResource($account))->response()->setStatusCode(201);
    }

    public function show(Account $account): AccountResource
    {
        Gate::authorize('view', $account);

        $account->load('subscription.plan');
        $account->loadCount('members');

        return new AccountResource($account);
    }

    public function update(Request $request, Account $account): AccountResource
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

        return new AccountResource($account);
    }
}
