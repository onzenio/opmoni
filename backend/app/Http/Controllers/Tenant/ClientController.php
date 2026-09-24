<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexClientRequest;
use App\Http\Requests\Tenant\StoreClientRequest;
use App\Http\Requests\Tenant\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ClientSheetResource;
use App\Models\Account;
use App\Models\Client;
use App\Services\ClientManager;
use App\Services\ClientPortfolio;
use App\Services\CnpjLookupException;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function __construct(private ClientManager $clients) {}

    public function index(IndexClientRequest $request, ClientPortfolio $portfolio): AnonymousResourceCollection
    {
        $data = $request->validated();
        $sort = $data['sort'] ?? 'name';
        $direction = $data['direction'] ?? 'asc';

        if ($request->boolean('sheet')) {
            return $this->sheet($portfolio, $data, $sort, $direction);
        }

        $perPage = (int) ($data['per_page'] ?? 25);

        $clients = $portfolio->sorted(
            $portfolio->filtered($data)->with(['currentCertificate', 'ecacPowerOfAttorney', 'tags']),
            $sort,
            $direction,
        );

        if ($request->boolean('all')) {
            $clients = $clients->limit((int) config('clients.sheet_limit'))->get();

            return ClientResource::collection($clients)->additional([
                'meta' => ['total' => $clients->count()],
            ]);
        }

        return ClientResource::collection($clients->paginate($perPage)->withQueryString());
    }

    /**
     * Compact portfolio for the grid. The full match set is returned up to the
     * sheet limit; larger portfolios stay paged. Selection snapshots are separate
     * and always cover the filter, including rows that were not loaded.
     *
     * @param  array<string, mixed>  $filters
     */
    private function sheet(ClientPortfolio $portfolio, array $filters, string $sort, string $direction): AnonymousResourceCollection
    {
        $filtered = $portfolio->filtered($filters);
        $total = (clone $filtered)->count();
        $query = $portfolio->sorted($this->sheetColumns($filtered), $sort, $direction);

        if ($total > (int) config('clients.sheet_limit')) {
            return ClientSheetResource::collection(
                $query->paginate((int) config('clients.sheet_page_size'))->withQueryString(),
            )->additional(['meta' => ['mode' => 'paged']]);
        }

        $clients = $query->get();

        return ClientSheetResource::collection($clients)->additional([
            'meta' => ['total' => $clients->count(), 'mode' => 'sheet'],
        ]);
    }

    /**
     * @param  Builder<Client>  $clients
     * @return Builder<Client>
     */
    private function sheetColumns(Builder $clients): Builder
    {
        return $clients->select([
            'clients.id',
            'clients.account_id',
            'clients.person_type',
            'clients.tax_id',
            'clients.name',
            'clients.status',
            'clients.tax_regime',
        ])->with([
            'currentCertificate' => fn (Relation $query) => $query->select([
                'client_certificates.id',
                'client_certificates.client_id',
                'client_certificates.valid_until',
            ]),
            'ecacPowerOfAttorney' => fn (Relation $query) => $query->select([
                'client_ecac_powers_of_attorney.id',
                'client_ecac_powers_of_attorney.client_id',
                'client_ecac_powers_of_attorney.expires_at',
            ]),
            'tags' => fn (Relation $query) => $query->select(['tags.id', 'tags.name', 'tags.color']),
        ]);
    }

    public function summary(IndexClientRequest $request, ClientPortfolio $portfolio): JsonResponse
    {
        return response()->json([
            'data' => $portfolio->counts($this->portfolioFilters($request)),
        ]);
    }

    public function analytics(IndexClientRequest $request, ClientPortfolio $portfolio): JsonResponse
    {
        return response()->json([
            'data' => $portfolio->analytics($this->portfolioFilters($request)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function portfolioFilters(IndexClientRequest $request): array
    {
        return $request->safe()->only([
            'q',
            'status',
            'tax_regime',
            'tag_id',
            'certificate_status',
            'poa_status',
            'deadline_status',
            'client_id',
        ]);
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

        return (new ClientResource($client->loadMissing(['currentCertificate', 'ecacPowerOfAttorney', 'tags'])))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        $client->loadMissing(['currentCertificate', 'ecacPowerOfAttorney', 'tags']);

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

        return new ClientResource($client->loadMissing(['currentCertificate', 'ecacPowerOfAttorney', 'tags']));
    }

    public function destroy(Request $request, Client $client): Response
    {
        Gate::authorize('delete', $client);

        $clientId = $client->getKey();
        $context = ['name' => $client->name, 'tax_id_last4' => substr((string) $client->tax_id, -4)];

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
