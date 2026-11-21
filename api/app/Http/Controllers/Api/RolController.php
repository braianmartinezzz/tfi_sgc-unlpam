<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RolService;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    public function index(RolService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->all(),
        ]);
    }
}
