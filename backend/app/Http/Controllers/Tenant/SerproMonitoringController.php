<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreNameRequest;
use App\Http\Resources\SerproMonitoringResource;
use App\Models\Account;
use App\Models\SerproMonitoring;
use App\Services\PlanLimits;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SerproMonitoringController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SerproMonitoring::class);

        return SerproMonitoringResource::collection(SerproMonitoring::query()->orderBy('name')->paginate(25));
    }

    public function store(StoreNameRequest $request): JsonResponse
    {
        Gate::authorize('create', SerproMonitoring::class);

        $account = Account::findOrFail(resolve(CurrentTenant::class)->accountId);
        PlanLimits::assertCanCreate($account, 'monitorings');

        $monitoring = SerproMonitoring::query()->create($request->validated());

        SupportAudit::logWrite($request, 'monitorings', 'create', $monitoring->getKey(), ['name' => $monitoring->name]);

        return (new SerproMonitoringResource($monitoring))->response()->setStatusCode(201);
    }

    public function show(SerproMonitoring $monitoring): SerproMonitoringResource
    {
        Gate::authorize('view', $monitoring);

        return new SerproMonitoringResource($monitoring);
    }

    public function update(Request $request, SerproMonitoring $monitoring): SerproMonitoringResource
    {
        Gate::authorize('update', $monitoring);

        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255']]);

        $monitoring->update($data);

        SupportAudit::logWrite($request, 'monitorings', 'update', $monitoring->getKey(), ['name' => $monitoring->name]);

        return new SerproMonitoringResource($monitoring);
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
