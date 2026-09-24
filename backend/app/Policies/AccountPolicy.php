<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Tenant\CurrentTenant;

class AccountPolicy
{
    /**
     * Account creation is restricted to super_admins (global panel or initial onboarding).
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Account $account): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->accountRole($account) !== null;
    }

    public function update(User $user, Account $account): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->tenantId() === $account->getKey()
            && $user->accountRole($account) === 'admin';
    }

    /**
     * Inviting or removing members requires the account `admin` role
     * within the current tenant (super_admin in support acts as admin).
     */
    public function manageMembers(User $user, Account $account): bool
    {
        return $this->tenantId() === $account->getKey() && $this->tenantRole($user) === 'admin';
    }

    /**
     * Reading the member directory is allowed for any tenant member
     * (super_admin in support acts as admin). No email is exposed.
     */
    public function viewMembers(User $user, Account $account): bool
    {
        return $this->tenantId() === $account->getKey() && $this->tenantRole($user) !== null;
    }

    private function tenantId(): ?int
    {
        return resolve(CurrentTenant::class)->accountId;
    }

    private function tenantRole(User $user): ?string
    {
        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return 'admin';
        }

        return $user->accountRole($tenantId);
    }
}
