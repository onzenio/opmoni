<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreClientSavedFilterRequest;
use App\Http\Resources\ClientSavedFilterResource;
use App\Models\ClientSavedFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientSavedFilterController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ClientSavedFilter::class);

        return ClientSavedFilterResource::collection(
            ClientSavedFilter::query()->orderBy('name')->get()
        );
    }

    public function store(StoreClientSavedFilterRequest $request): JsonResponse
    {
        $filter = ClientSavedFilter::query()->create([
            ...$request->safe()->only(['name', 'q', 'filters']),
            'user_id' => $request->user()->getKey(),
        ]);

        return (new ClientSavedFilterResource($filter))->response()->setStatusCode(201);
    }

    public function destroy(ClientSavedFilter $savedFilter): Response
    {
        Gate::authorize('delete', $savedFilter);
        $savedFilter->delete();

        return response()->noContent();
    }
}
