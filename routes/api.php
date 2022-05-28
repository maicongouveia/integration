<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mercadolivre;
use App\Http\Controllers\MercadolivreWebhook;
use App\Http\Controllers\RoutineController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/webhook/mercadolivre', [MercadolivreWebhook::class, 'receive']);

Route::get('/orders', [MercadoLivre::class, 'getOrdersForFront']);

Route::get('/routine', [RoutineController::class, 'getOrders']);
