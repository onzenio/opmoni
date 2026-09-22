<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportAccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
        ]);

        $logs = SupportAccessLog::with(['superAdmin', 'account'])
            ->when($data['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($logs);
    }
}
