<?php

namespace App\Http\Controllers;

use App\Classes\Bling;
use App\Classes\BlingPayment;
use App\Classes\MercadoLivre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadolivreWebhook extends Controller
{
    public function receive(Request $request) { 

        Log::info("[Webhook endpoint] Body Request: " . json_encode($request->all()));

        $mercadoPago = new MercadoLivre();
        $payment = $mercadoPago->getPayment($request['resource']);

        if(!$payment){
            Log::error("Internal Server Error. Error: getPayment()");
            return response(["message" => "Internal Server Error. Error: getPayment()"], 500);
        }

        $blingPayment = new BlingPayment($payment);
        $bills = $blingPayment->getBills();

        $blingAPI = new Bling();
        $response = $blingAPI->registerBills($bills);

        if ($response) {            
            return response(200);
        }
        
        return response(500);
    }


    
    // Todo: Guardar pagamento no banco de dados

    // Todo: Tratar pagamento e criar contas a pagar e receber

    // Todo: Enviar contas para bling
}
