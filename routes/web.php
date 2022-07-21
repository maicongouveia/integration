<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MercadoLivreOrderController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

//Route::get('/', [MercadoLivreOrderController::class, "orders"]);
/* Route::get('/contatos', function () {

    $request_data = array(
        'apikey' => env('BLING_API_KEY'),
    );

    $response = Http::get('https://bling.com.br/b/Api/v2/contatos/json', $request_data);

    foreach($response->json()['retorno']['contatos'] as $contato) {
        $contato = $contato['contato'];
        echo "<br> " . "Nome: " . $contato['nome'] . "<br>";
    }

}); */

Route::get('/excluirPedido/{id}', function ($id) {
    DB::table('shipping_refund')->where('order_id' , $id)->delete();
    DB::table('buyer')->where('order_id' , $id)->delete();
    DB::table('fee')->where('order_id' , $id)->delete();
    DB::table('payment')->where('order_id' , $id)->delete();
    DB::table('order')->where('id' , $id)->delete();

    return "Pedido $id excluido";
});

Route::get('/rotina', function (){

    $config = DB::table('config')->first();

    if($config->schedule_on){
        DB::table('config')->update(['schedule_on' => 0]);
        Log::info('-----------------');
        Log::info('[ROTINA] [DESLIGADA]');
        Log::info('-----------------');
        return "<center><h1>Rotina desligada</h1></center>";
    } else {
        DB::table('config')->update(['schedule_on' => 1]);
        Log::info('-----------------');
        Log::info('[ROTINA] [LIGADA]');
        Log::info('-----------------');
        return "<center><h1>Rotina ligada</h1></center>";
    }
});

Route::get('/pedidos', function () {

    return DB::table('order')->get();

});
