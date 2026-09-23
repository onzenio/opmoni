<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\Tenant\CurrentTenant;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantRole($user) !== null;
    }

    public function create(User $user): bool
    {
        return in_array($this->tenantRole($user), ['admin', 'operador'], true);
    }

    public function update(User $user, Tag $tag): bool
    {
        return in_array($this->tenantRole($user), ['admin', 'operador'], true)
            && $this->isTenantModel($tag->account_id);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return in_array($this->tenantRole($user), ['admin', 'operador'], true)
            && $this->isTenantModel($tag->account_id);
    }

    private function isTenantModel(int $accountId): bool
    {
        return resolve(CurrentTenant::class)->accountId === $accountId;
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
