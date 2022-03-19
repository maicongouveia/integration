<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Mercadolivre extends Controller
{
    public function __construct()
    {
        $this->url = env('MERCADOPAGO_API_URL');
    }

    public function getPayer($payerId)
    {
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
    
    public function getOrders(Request $request) 
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => 1,
            'sort'   => 'date_desc',
            'order.status' => 'paid',
            //'q'      => 5332358230//5332358229 //order_id
        );

        if ($request->order_id) {
            $request_data['q'] = $request->order_id;
        }   

        if ($request->offset) {
            $request_data['offset'] = $request->offset;
        }

        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOLIVRE_API_URL")."/orders/search", $request_data);
            if ($response->status() != 200) {
                Log::warning("[getOrders]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return Response($this->responseOrdersHandler($response->json()), 200);
            //return Response($response->json(), 200);
        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            dd("[getOrders]: " . $e->getMessage());
            return null;
        }
    }

    public function getShippingCost($shippingId)
    {
        try{
            $url = env("MERCADOLIVRE_API_URL")."/shipments/".$shippingId;
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url);
            if($response->status() != 200) {
                Log::warning("[getShippingCost]: Status: " . $response->status() . " - Body: " . $response);
                return null;
            }
            return array(
                'description' => 'Envio pelo Mercado Envios',
                'amount' => $response['shipping_option']['list_cost'],
            );
        }catch(Exception $e){
            Log::error("[getShippingCost]: " . $e->getMessage());
            dd("[getShippingCost]: " . $e->getMessage());
            return null;
        }
    }

    public function getPaymentDetails($payments)
    {
        $paymentResponse = array();
        $paymentResponse['sales_fee'] = array();
        $paymentResponse['payment_info'] = array();

        foreach ($payments as $payment) {
            try{
                $url = env("MERCADOPAGO_API_URL") . "/v1/payments/" . $payment['id'];
                $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))
                            ->get($url);

                if ($response->status() != 200) {
                    $log =  "[getPaymentDetails]:PaymentId: " . $payment['id'] .
                            " Status: " . $response->status() . 
                            " - Body: " . $response->body();
                    Log::warning($log);
                    return null;
                }

                $response = array(
                    'sales_fee' => $this->responseFeeHandler($response->json()),
                    'payment_info'  =>  array(
                        'method' => $response['payment_method_id'],
                        'amount' => $response['transaction_amount'],
                    ),
                );
                
            }catch(Exception $e){
                $log = "[getPaymentDetails]: " . $e->getMessage() . 
                       "- [Data]: " . $response;
                Log::error($log);
                dd($log);
                return null;
            }

            $sales_fee = array_merge(
                $paymentResponse['sales_fee'], 
                $response['sales_fee']
            );

            $paymentResponse['sales_fee'] = $sales_fee;

            $payment_info = array_merge(
                $paymentResponse['payment_info'], 
                $response['payment_info']
            );

            $paymentResponse['payment_info'][] = $payment_info;
            
        }

        return $paymentResponse;
        
    }

    public function responseFeeHandler($responseFeeDetails) 
    {
        $feeDetails = array();

        $typeDict = array(
            'ml_fee' => 'Gestão de Vendas',
            'mp_fee' => 'Tarifa de Venda',
        );

        foreach ($responseFeeDetails['fee_details'] as $fee) {
            $description = $fee['type'];

            $isType = array_key_exists($fee['type'], $typeDict);

            if ($isType) { 
                $description = $typeDict[$fee['type']];

                try{
                    $feeDetails[] = array(
                        'amount'      => $fee['amount'],
                        'description' => $description
                    );
                }
                catch(Exception $e){
                    Log::error("[responseFeeHandler]: " . $e->getMessage(). " - [Data]: " . $fee);
                    dd("[responseFeeHandler]: " . $e->getMessage() . "- [Data]: " . $fee);
                }
            }

            
        }

        return $feeDetails;
    }

    public function responseBuyerHandler($buyer)
    {
        //dd($buyer);
        return array(
            'full_name'      => $buyer['nickname'],
            'email'          => (isset($buyer['email'])) ? $buyer['email'] : "",
            'identification' => (isset($buyer['identification'])) ? $buyer['identification'] : "",
            'phone'          => (isset($buyer['phone'])) ? $buyer['phone'] : "",
        );
    }

    public function getInvoice($orderId)
    {
        try{          
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get(env("MERCADOLIVRE_API_URL") . "/users/" . env('MERCADOLIVRE_SELLER_ID') . "/invoices/orders/" . $orderId);
            if($response->status() != 200) {
                Log::warning("[getInvoice]: Order ID: $orderId - Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $response->json()['invoice_number'];
        }catch(Exception $e){
            Log::error("[getInvoice]: Order ID: $orderId - [Error]" . $e->getMessage());
            dd("[getInvoice]: Order ID: $orderId - [Error]" . $e->getMessage());
            return null;
        }
    }

    public function responseOrdersHandler($responseOrders) 
    {
        $orders = array(); 
        
        foreach ($responseOrders['results'] as $order) {
            //dd($order['buyer']);
            $payments = $order['payments'];            

            $input = [
                'order_id'     => $order['id'],
                'invoice'      => $this->getInvoice($order['id']),
                'reason'       => $payments[0]['reason'],
                'payment_date' => $payments[0]['date_approved'],
                'buyer'        => $this->responseBuyerHandler($order['buyer']),
            ];

            $input = array_merge($input, $this->getPaymentDetails($payments));
            
            $input['sales_fee'][] = $this->getShippingCost($order['shipping']['id']);

            $orders[] = $input;
        }
        return $orders;
    }
}