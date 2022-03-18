<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mercadolivre;
use App\Http\Controllers\MercadolivreWebhook;

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

Route::get('/mercadolivre/orders', [MercadoLivre::class, 'getOrders']);

Route::get('/mercadolivre/order/{order_id}', [MercadoLivre::class, 'getOrders']);