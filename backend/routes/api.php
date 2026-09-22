<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\SupportLogController as AdminSupportLogController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SupportAccessController;
use App\Http\Controllers\Tenant\AccountMemberController;
use App\Http\Controllers\Tenant\AccountSwitchController;
use App\Http\Controllers\Tenant\ClientController;
use App\Http\Controllers\Tenant\DocumentController;
use App\Http\Controllers\Tenant\ProcessController;
use App\Http\Controllers\Tenant\SerproMonitoringController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::post('/account/switch', AccountSwitchController::class)->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('monitorings', SerproMonitoringController::class);
    Route::apiResource('documents', DocumentController::class);
    Route::apiResource('processes', ProcessController::class);
    Route::apiResource('account/members', AccountMemberController::class)->parameter('members', 'member');
});

Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function (): void {
    Route::apiResource('accounts', AdminAccountController::class)->except(['destroy']);
    Route::apiResource('plans', AdminPlanController::class)->except(['destroy']);
    Route::apiResource('subscriptions', AdminSubscriptionController::class)->only(['index', 'show', 'update']);
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('support/logs', [AdminSupportLogController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'super_admin'])
    ->post('support/accounts/{account}/enter', [SupportAccessController::class, 'enter']);
Route::middleware(['auth:sanctum', 'super_admin'])
    ->post('support/exit', [SupportAccessController::class, 'exit']);
