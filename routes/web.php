<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MercadoLivreOrderController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/* Route::get('/', function () {
    return view('welcome');
});
 */


Route::get('/', [MercadoLivreOrderController::class, "orders"]);
Route::get('/cancel', [MercadoLivreOrderController::class, "orders"]);
Route::get('/contatos', function () {

    $request_data = array(
        'apikey' => env('BLING_API_KEY'),
    );

    $response = Http::get('https://bling.com.br/b/Api/v2/contatos/json', $request_data);

    foreach($response->json()['retorno']['contatos'] as $contato) {
        $contato = $contato['contato'];
        echo "<br> " . "Nome: " . $contato['nome'] . "<br>";
    }

});
