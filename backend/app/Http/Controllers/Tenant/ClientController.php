<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexClientRequest;
use App\Http\Requests\Tenant\StoreClientRequest;
use App\Http\Requests\Tenant\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Account;
use App\Models\Client;
use App\Services\ClientManager;
use App\Services\CnpjLookupException;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function __construct(private ClientManager $clients) {}

    public function index(IndexClientRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $sort = $data['sort'] ?? 'name';
        $direction = $data['direction'] ?? 'asc';
        $perPage = (int) ($data['per_page'] ?? 15);

        $clients = Client::query()
            ->search($data['q'] ?? null)
            ->withStatus($data['status'] ?? null)
            ->withTaxRegime($data['tax_regime'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $account = Account::findOrFail(resolve(CurrentTenant::class)->accountId);

        try {
            $client = $this->clients->create($account, $request->validated());
        } catch (CnpjLookupException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        SupportAudit::logWrite($request, 'clients', 'create', $client->getKey(), $this->auditContext($client));

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return new ClientResource($client);
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource|JsonResponse
    {
        try {
            $client = $this->clients->update($client, $request->validated());
        } catch (CnpjLookupException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        SupportAudit::logWrite($request, 'clients', 'update', $client->getKey(), $this->auditContext($client));

        return new ClientResource($client);
    }

    public function destroy(Request $request, Client $client): Response
    {
        Gate::authorize('delete', $client);

        $clientId = $client->getKey();
        $context = $this->auditContext($client);

        $this->clients->delete($client);

        SupportAudit::logWrite($request, 'clients', 'delete', $clientId, $context);

        return response()->noContent();
    }

    /**
     * @return array<string, string|null>
     */
    private function auditContext(Client $client): array
    {
        return [
            'person_type' => $client->person_type?->value,
            'status' => $client->status?->value,
        ];
    }
}
