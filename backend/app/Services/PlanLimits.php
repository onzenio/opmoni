<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

class PlanLimits
{
    /**
     * Throw when creating another `$key` record would exceed the
     * account's active plan limit. Absent limit means unlimited.
     */
    public static function assertCanCreate(Account $account, string $key): void
    {
        $limit = $account->subscription->plan->limits[$key] ?? null;

        if ($limit === null) {
            return;
        }

        $count = match ($key) {
            'users' => $account->members()->count(),
            'clients' => $account->clients()->count(),
            'monitorings' => $account->monitorings()->count(),
        };

        if ($count >= $limit) {
            throw ValidationException::withMessages(['limit' => ['Limite do plano atingido.']]);
        }
    }
}
