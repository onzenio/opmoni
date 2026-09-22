<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreClientCertificateRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\ClientCertificateVault;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientCertificateController extends Controller
{
    public function __construct(private ClientCertificateVault $vault) {}

    public function store(StoreClientCertificateRequest $request, Client $client): ClientResource
    {
        Gate::authorize('update', $client);

        $validated = $request->validated();

        try {
            $this->vault->replace($client, $validated['certificate'], (string) $validated['password']);
        } finally {
            $validated['password'] = '';
            unset($validated);
        }

        return new ClientResource($client->fresh(['currentCertificate', 'ecacPowerOfAttorney']));
    }

    public function destroy(Client $client): Response
    {
        Gate::authorize('update', $client);

        $this->vault->remove($client);

        return response()->noContent();
    }
}
