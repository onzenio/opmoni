<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Services\SupportAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProcessController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Process::class);

        return response()->json(Process::all());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Process::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $process = Process::create($data);

        SupportAudit::logWrite($request, 'processes', 'create', $process->getKey(), ['name' => $process->name]);

        return response()->json($process, 201);
    }

    public function show(Process $process): JsonResponse
    {
        Gate::authorize('view', $process);

        return response()->json($process);
    }

    public function update(Request $request, Process $process): JsonResponse
    {
        Gate::authorize('update', $process);

        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255']]);

        $process->update($data);

        SupportAudit::logWrite($request, 'processes', 'update', $process->getKey(), ['name' => $process->name]);

        return response()->json($process);
    }

    public function destroy(Request $request, Process $process): Response
    {
        Gate::authorize('delete', $process);

        $processId = $process->getKey();
        $processName = $process->name;
        $process->delete();

        SupportAudit::logWrite($request, 'processes', 'delete', $processId, ['name' => $processName]);

        return response()->noContent();
    }
}
