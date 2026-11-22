<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function index(Request $request, PanelService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->resumen($request->user()),
        ]);
    }
}
