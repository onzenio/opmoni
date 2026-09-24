<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreNameRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\SupportAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Document::class);

        return DocumentResource::collection(Document::query()->orderBy('name')->paginate(25));
    }

    public function store(StoreNameRequest $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $document = Document::query()->create($request->validated());

        SupportAudit::logWrite($request, 'documents', 'create', $document->getKey(), ['name' => $document->name]);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        return new DocumentResource($document);
    }

    public function update(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document);

        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255']]);

        $document->update($data);

        SupportAudit::logWrite($request, 'documents', 'update', $document->getKey(), ['name' => $document->name]);

        return new DocumentResource($document);
    }

    public function destroy(Request $request, Document $document): Response
    {
        Gate::authorize('delete', $document);

        $documentId = $document->getKey();
        $documentName = $document->name;
        $document->delete();

        SupportAudit::logWrite($request, 'documents', 'delete', $documentId, ['name' => $documentName]);

        return response()->noContent();
    }
}
