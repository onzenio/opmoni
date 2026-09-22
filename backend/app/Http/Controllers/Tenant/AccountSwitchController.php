<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSwitchController extends Controller
{
    /**
     * Switch the authenticated user's current account.
     *
     * Regular users may only switch to accounts they belong to;
     * super_admins may target any account (support enter/exit with
     * audit logging lives in Task 6).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $account = Account::findOrFail($data['account_id']);
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->accountRole($account) === null) {
            abort(403, 'Você não pertence a esta conta.');
        }

        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return response()->json($user->currentAccount);
    }
}
