<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditoriaIndexRequest;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;

class AuditoriaController extends Controller
{
    public function index(AuditoriaIndexRequest $request, AuditoriaService $service): JsonResponse
    {
        return response()->json($service->paginate($request->validated()));
    }
}
