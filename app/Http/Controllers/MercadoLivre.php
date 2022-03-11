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

    public function getPayer($payerId){
        try{
            $response = Http::withHeaders($this->headers)->
                        get('https://api.mercadopago.com/v1/customers/' . $payerId, $this->headers);

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
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => 10,
            'sort'   => 'date_desc',
            'q'      => 5265607923 //order_id
        );
        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOLIVRE_API_URL")."/orders/search", $request_data);
            if($response->status() != 200) {
                Log::warning("[getOrders]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $this->responseOrdersHandler($response->json());
        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            dd("[getOrders]: " . $e->getMessage());
            return null;
        }
    }

    public function getShippingCost($shippingId){
        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOLIVRE_API_URL")."/shipments/".$shippingId);
            if($response->status() != 200) {
                Log::warning("[getShippingCost]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $response['shipping_option']['list_cost'];
        }catch(Exception $e){
            Log::error("[getShippingCost]: " . $e->getMessage());
            dd("[getShippingCost]: " . $e->getMessage());
            return null;
        }
    }

    public function getPaymentDetails($paymentId){
        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOPAGO_API_URL") . "/v1/payments/" . $paymentId);
            if($response->status() != 200) {
                Log::warning("[getFeeDetails]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return array(
                'sales_fee' => $this->responseFeeHandler($response->json()),
                'payer' => $this->responsePayerHandler($response->json()),
            );
        }catch(Exception $e){
            Log::error("[getFeeDetails]: " . $e->getMessage());
            dd("[getFeeDetails]: " . $e->getMessage());
            return null;
        }
    }

    public function responseFeeHandler($responseFeeDetails){
        $feeDetails = array();

        $typeDict = array(
            'ml_fee' => 'Gestão de Vendas',
            'mp_fee' => 'Tarifa de Venda',
        );

        foreach($responseFeeDetails['fee_details'] as $fee){
            $feeDetails[] = [
                'amount' => $fee['amount'],
                'description' => $typeDict[$fee['type']]
            ];
        }

        

        return $feeDetails;
    }

    public function responsePayerHandler($responsePayment){
        return $responsePayment['payer']['first_name'] . " " . $responsePayment['payer']['last_name'];
    }

    public function getInvoice($orderId){
        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOLIVRE_API_URL") . "/users/" . env('MERCADOLIVRE_SELLER_ID') . "/invoices/orders/" . $orderId);
            if($response->status() != 200) {
                Log::warning("[getInvoice]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $response->json()['invoice_number'];
        }catch(Exception $e){
            Log::error("[getInvoice]: " . $e->getMessage());
            dd("[getInvoice]: " . $e->getMessage());
            return null;
        }
    }

    public function responseOrdersHandler($responseOrders) {
        $orders = array(); 
        
        foreach($responseOrders['results'] as $order) {
            //dd($order);
            $payment = $order['payments']['0'];
            $input = [
                'order_id' => $payment['order_id'],
                //'reason'   => $payment['reason'],
                'payment_method' => $payment['payment_method_id'],
                'payment_date' => $payment['date_approved'],
                'total_paid_amount' => $payment['total_paid_amount'],
                'shipping_cost' => $this->getShippingCost($order['shipping']['id']),
                'invoice' => $this->getInvoice($payment['order_id']),
            ];

            $orders[] = array_merge($input, $this->getPaymentDetails($payment['id']));
        }
        return $orders;
    }
}
