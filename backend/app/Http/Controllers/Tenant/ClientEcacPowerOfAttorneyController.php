<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpsertClientEcacPowerOfAttorneyRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientEcacPowerOfAttorneyController extends Controller
{
    public function update(UpsertClientEcacPowerOfAttorneyRequest $request, Client $client): ClientResource
    {
        $client->ecacPowerOfAttorney()->updateOrCreate(
            ['client_id' => $client->getKey()],
            array_merge($request->validated(), ['account_id' => $client->account_id])
        );

        return new ClientResource($client->fresh(['currentCertificate', 'ecacPowerOfAttorney']));
    }

    public function destroy(Client $client): Response
    {
        Gate::authorize('update', $client);

        $client->ecacPowerOfAttorney()->delete();

        return response()->noContent();
    }
}
