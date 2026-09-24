<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Tenant\CurrentTenant;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantRole($user) !== null;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->tenantRole($user) !== null && $this->isTenantModel($task->account_id);
    }

    public function create(User $user): bool
    {
        return in_array($this->tenantRole($user), ['admin', 'operador'], true);
    }

    public function update(User $user, Task $task): bool
    {
        return in_array($this->tenantRole($user), ['admin', 'operador'], true)
            && $this->isTenantModel($task->account_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return in_array($this->tenantRole($user), ['admin', 'operador'], true)
            && $this->isTenantModel($task->account_id);
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
