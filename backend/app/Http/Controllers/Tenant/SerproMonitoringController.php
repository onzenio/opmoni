<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\SerproMonitoring;
use App\Services\PlanLimits;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SerproMonitoringController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', SerproMonitoring::class);

        return response()->json(SerproMonitoring::all());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', SerproMonitoring::class);

        $account = Account::findOrFail(resolve(CurrentTenant::class)->accountId);
        PlanLimits::assertCanCreate($account, 'monitorings');

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $monitoring = SerproMonitoring::create($data);

        SupportAudit::logWrite($request, 'monitorings', 'create', $monitoring->getKey(), ['name' => $monitoring->name]);

        return response()->json($monitoring, 201);
    }

    public function show(SerproMonitoring $monitoring): JsonResponse
    {
        Gate::authorize('view', $monitoring);

        return response()->json($monitoring);
    }

    public function update(Request $request, SerproMonitoring $monitoring): JsonResponse
    {
        Gate::authorize('update', $monitoring);

        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255']]);

        $monitoring->update($data);

        SupportAudit::logWrite($request, 'monitorings', 'update', $monitoring->getKey(), ['name' => $monitoring->name]);

        return response()->json($monitoring);
    }

    public function destroy(Request $request, SerproMonitoring $monitoring): Response
    {
        Gate::authorize('delete', $monitoring);

        $monitoringId = $monitoring->getKey();
        $monitoringName = $monitoring->name;
        $monitoring->delete();

        SupportAudit::logWrite($request, 'monitorings', 'delete', $monitoringId, ['name' => $monitoringName]);

        return response()->noContent();
    }
}
