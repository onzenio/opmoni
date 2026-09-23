<?php

namespace App\Policies;

use App\Models\ClientSavedFilter;
use App\Models\User;
use App\Tenant\CurrentTenant;

class ClientSavedFilterPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantRole($user) !== null;
    }

    public function create(User $user): bool
    {
        return $this->tenantRole($user) !== null;
    }

    public function delete(User $user, ClientSavedFilter $filter): bool
    {
        return $this->tenantRole($user) !== null
            && $filter->user_id === $user->getKey()
            && resolve(CurrentTenant::class)->accountId === $filter->account_id;
    }

    private function tenantRole(User $user): ?string
    {
        $tenantId = resolve(CurrentTenant::class)->accountId;

        if ($tenantId === null) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return 'admin';
        }

        return $user->accountRole($tenantId);
    }
}
