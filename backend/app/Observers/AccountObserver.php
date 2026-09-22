<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\Plan;

class AccountObserver
{
    /**
     * Handle the Account "created" event.
     */
    public function created(Account $account): void
    {
        $account->subscription()->create(['plan_id' => Plan::bySlug('basico')->getKey()]);
    }
}
