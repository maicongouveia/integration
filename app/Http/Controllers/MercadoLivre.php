<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Mercadolivre extends Controller
{
    public function __construct()
    {
        $this->url = env('MERCADOPAGO_API_URL');
    }

    public function getPayment(){
        try{
            //$response = Http::withHeaders($this->headers)->get($this->url . $endpoint);            
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get("https://api.mercadolibre.com/orders/search?seller=156387968");
            if($response->status() != 200) {
                Log::warning("[getPayment]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return dd($response->json());//['results'][0]);
        }catch(Exception $e){
            Log::error("[getPayment]: " . $e->getMessage());
            dd($e->getMessage());
            return null;
        }
    }

    public function getPayer(Int $payer_id){
        try{
            $response = Http::withHeaders($this->headers)->
                        get('https://api.mercadopago.com/v1/customers/' . $payer_id, $this->headers);

            if($response->status() != 200) {
                Log::warning("[getPayer]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $response->json();
        }catch(Exception $e){
            Log::error("[getPayer]: " . $e->getMessage());
            return null;
        }
    }

    
    public function getOrders(){
        $request_data = array(
            'seller' => 156387968,
            'limit'  => 10,
            'sort'   => 'date_desc'
        );
        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOLIVRE_API_URL")."/orders/search", $request_data);
            if($response->status() != 200) {
                Log::warning("[getPayment]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $this->responseOrdersHandler($response->json());
        }catch(Exception $e){
            Log::error("[getPayment]: " . $e->getMessage());
            dd("[getPayment]: " . $e->getMessage());
            return null;
        }
    }

    public function responseOrdersHandler($responseOrders) {
        $orders = array(); 
        
        foreach($responseOrders['results'] as $order) {
            //dd($order);
            $payment = $order['payments']['0'];
            $orders[] = [
                'order_id' => $payment['order_id'],
                'reason'   => $payment['reason'],
                'total_paid_amount' => $payment['total_paid_amount'],
                'payer_id' => $payment['payer_id'],
                'sale_fee' => $order['order_items'][0]['sale_fee']
            ];
        }
        return $orders;
    }
}
