<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\SupportAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        return response()->json(Document::all());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $document = Document::create($data);

        SupportAudit::logWrite($request, 'documents', 'create', $document->getKey(), ['name' => $document->name]);

        return response()->json($document, 201);
    }

    public function show(Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        return response()->json($document);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255']]);

        $document->update($data);

        SupportAudit::logWrite($request, 'documents', 'update', $document->getKey(), ['name' => $document->name]);

        return response()->json($document);
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
