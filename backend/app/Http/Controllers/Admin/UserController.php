<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('accountLinks.account')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($users);
    }
}
