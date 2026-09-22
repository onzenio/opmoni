<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Services\PlanLimits;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Client::class);

        return response()->json(Client::all());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Client::class);

        $account = Account::findOrFail(resolve(CurrentTenant::class)->accountId);
        PlanLimits::assertCanCreate($account, 'clients');

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $client = Client::create($data);

        SupportAudit::logWrite($request, 'clients', 'create', $client->getKey(), ['name' => $client->name]);

        return response()->json($client, 201);
    }

    public function show(Client $client): JsonResponse
    {
        Gate::authorize('view', $client);

        return response()->json($client);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        Gate::authorize('update', $client);

        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255']]);

        $client->update($data);

        SupportAudit::logWrite($request, 'clients', 'update', $client->getKey(), ['name' => $client->name]);

        return response()->json($client);
    }

    public function destroy(Request $request, Client $client): Response
    {
        Gate::authorize('delete', $client);

        $clientId = $client->getKey();
        $clientName = $client->name;
        $client->delete();

        SupportAudit::logWrite($request, 'clients', 'delete', $clientId, ['name' => $clientName]);

        return response()->noContent();
    }
}
