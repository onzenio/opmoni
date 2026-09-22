<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\LookupClientCnpjRequest;
use App\Services\CnpjLookupException;
use App\Services\CnpjWsLookup;
use Illuminate\Http\JsonResponse;

class ClientCnpjLookupController extends Controller
{
    public function __construct(private CnpjWsLookup $lookup) {}

    public function __invoke(LookupClientCnpjRequest $request): JsonResponse
    {
        try {
            $result = $this->lookup->lookup((string) $request->validated()['tax_id']);
        } catch (CnpjLookupException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['data' => $result]);
    }
}
