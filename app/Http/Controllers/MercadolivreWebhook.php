<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadolivreWebhook extends Controller
{
    public function receive(Request $request) {

        $requestBody = [
            "resource" => "/collections/3043111111",
            "user_id" => 123456789,
            "topic" => "payments",
            "application_id" => 2069392825111111,
            "attempts" => 1,
            "sent" => "2017-10-09T13:58:22.081Z",
            "received" => "2017-10-09T13:58:22.061Z"            
        ];

        $mercadoPago = new MercadoLivre();
        $payment = $mercadoPago->getPayment($requestBody['resource']);

        /* if(!$payment){
            Log::error("Internal Server Error. Error: getPayment()");
            return response(["message" => "Internal Server Error. Error: getPayment()"], 500);
        } */ 
        
        $payment = [];

        return response(200);
    }
}
