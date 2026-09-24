<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Services\PlanLimits;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AccountMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $account = $this->tenantAccount();
        Gate::authorize('manageMembers', $account);

        return response()->json($this->presentMembers($account));
    }

    public function store(Request $request): JsonResponse
    {
        $account = $this->tenantAccount();
        Gate::authorize('manageMembers', $account);
        PlanLimits::assertCanCreate($account, 'users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,operador,user'],
        ]);

        $member = new User;
        $member->name = $data['name'];
        $member->email = $data['email'];
        $member->password = $data['password'];
        $member->current_account_id = $account->getKey();
        $member->save();

        $account->members()->attach($member, [
            'role' => $data['role'],
            'inviter_id' => $request->user()->getKey(),
        ]);

        SupportAudit::logWrite($request, 'members', 'create', $member->getKey(), [
            'email' => $member->email,
            'role' => $data['role'],
        ]);

        return response()->json($this->presentMember($member, $data['role']), 201);
    }

    public function directory(): JsonResponse
    {
        $account = $this->tenantAccount();
        Gate::authorize('viewMembers', $account);

        $rows = $account->members()->with('departments')->orderBy('name')->get()
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'role' => $member->pivot->role,
                'departments' => $member->departments->sortBy('name')->map(fn ($department): array => [
                    'id' => $department->getKey(),
                    'name' => $department->name,
                    'color' => $department->color,
                ])->values()->all(),
            ])->all();

        return response()->json(['data' => $rows]);
    }

    public function show(User $member): JsonResponse
    {
        $account = $this->tenantAccount();
        Gate::authorize('manageMembers', $account);

        $role = $member->accountRole($account);

        if ($role === null) {
            abort(404);
        }

        return response()->json($this->presentMember($member, $role));
    }

    public function update(Request $request, User $member): JsonResponse
    {
        $account = $this->tenantAccount();
        Gate::authorize('manageMembers', $account);

        if ($member->accountRole($account) === null) {
            abort(404);
        }

        $data = $request->validate(['role' => ['required', 'in:admin,operador,user']]);

        $account->members()->updateExistingPivot($member, ['role' => $data['role']]);

        SupportAudit::logWrite($request, 'members', 'update', $member->getKey(), ['role' => $data['role']]);

        return response()->json($this->presentMember($member->refresh(), $data['role']));
    }

    public function destroy(Request $request, User $member): Response
    {
        $account = $this->tenantAccount();
        Gate::authorize('manageMembers', $account);

        if ($member->accountRole($account) === null) {
            abort(404);
        }

        $memberId = $member->getKey();
        $account->members()->detach($member);

        SupportAudit::logWrite($request, 'members', 'delete', $memberId, ['email' => $member->email]);

        return response()->noContent();
    }

    private function tenantAccount(): Account
    {
        return Account::findOrFail(resolve(CurrentTenant::class)->accountId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presentMembers(Account $account): array
    {
        return $account->members()->get()->map(fn (User $member): array => $this->presentMember(
            $member,
            $member->pivot->role
        ))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMember(User $member, string $role): array
    {
        return [
            'id' => $member->getKey(),
            'name' => $member->name,
            'email' => $member->email,
            'role' => $role,
        ];
    }
}
