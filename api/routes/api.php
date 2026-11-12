<?php

use App\Http\Controllers\Api\ConvenioController;
use Illuminate\Support\Facades\Route;

Route::post('/convenios', [ConvenioController::class, 'store']);
