<?php
namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Payment;
use Exception;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class RoutineController extends Controller
{
    public $limit = 50;

    public function getOrders($offset = null)
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => $this->limit,
            'sort'   => 'date_desc',
            'order.status' => 'paid',
            //'q'      => 5265607923//5332358230//5332358229 //order_id
        );

        /* 
        if ($request->order_id) {
            $request_data['q'] = $request->order_id;
        }   
        */

        if ($offset) {
            $request_data['offset'] = $offset;
        } 

        try{
            $url = env("MERCADOLIVRE_API_URL") . "/orders/search"; 

            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))
                        ->get($url, $request_data);

            if ($response->status() != 200) {
                Log::warning("[getOrders]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            
            return $response->json()['results'];

        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            dd("[getOrders]: " . $e->getMessage());
            return null;
        }
    }
    
    public function routine(Request $request)
    {
        $o = array();

        $offset = 0;

        $date = "2022-03-20";//date('Y-m-d');

        $stopFlag = false;

        while (!$stopFlag) {
            $orders = $this->getOrders($offset);

            foreach ($orders as $order) {
                
                $format_date = new DateTime($order['date_created']);
                $order_date = date_format($format_date, 'Y-m-d');

                if ($date == $order_date) {
                    $i = [
                        'order_id'    => $order['id'],
                        'created_in'  => $order['date_created'],
                        'buyer'       => $order['buyer']['nickname'],
                        'shipping_id' => $order['shipping']['id'],
                    ];

                    $i['payments'] = array();

                    foreach ($order['payments'] as $payment) {
                        $i['payments'][] = ['id' => $payment['id']];
                    }                   

                    $o[$i['order_id']] = $i;

                } else {
                    $stopFlag = true;
                    break;
                }
            }

            $offset++;
            
        }

        $orders = $o;

        if ($orders) {
            //Cadastra pedidos que ainda não estão na base
            foreach ($orders as $order) {
                $exists = Order::where('order_id', $order['order_id'])->first();

                if (!$exists) {
                    $date = new DateTime($order['created_in']);
                    Order::create(
                        [
                            'order_id'         => $order['order_id'],
                            'invoice'          => null,
                            'payment_date'     => $date->format('Y-m-d H:i:s'),
                            'need_update_flag' => 1,
                        ]
                    );
                }
            }

            //Preenche dados que faltam
            $orders_registered = Order::where('need_update_flag', true)
                ->take(2)->get();

            $mercadoLivre = new Mercadolivre();

            foreach ($orders_registered as $order) {

                //Pegar Nota Fiscal
                $invoice_number = $mercadoLivre->getInvoice($order['order_id']);

                if ($invoice_number) {
                    Order::where('id', $order['id'])
                    ->update(['invoice' => $invoice_number]);
                }
                
                $payments = $orders[$order['order_id']]['payments'];

                $paymentDetails = $mercadoLivre->getPaymentDetails($payments);

                //Buyer

                if ($paymentDetails['payer']['first_name'] == "Splitter") {
                    Buyer::create(
                        [
                            'order_id' => $order['id'],
                            'name' => $orders[$order['order_id']]['buyer'],
                        ]
                    );
                    unset($paymentDetails['payer']);
                } else {
                    $payer = $paymentDetails['payer'];

                    $phone = "";

                    if ($payer['phone']['area_code']) {
                        $phone = $phone . $payer['phone']['area_code'];
                    }

                    if ($payer['phone']['extension']) {
                        $phone = $phone . $payer['phone']['extension'];
                    }

                    if ($payer['phone']['number']) {
                        $phone = $phone . $payer['phone']['number'];
                    }

                    Buyer::create(
                        [
                            'order_id' => $order['id'],
                            'name' => $payer['first_name'] . " "  . $payer['last_name'],
                            'email' => $payer['email'],
                            'identificationType' => $payer['identification']['type'],
                            'identificationNumber' => $payer['identification']['number'],
                            'phone' => $phone,
                        ]
                    );
                }

                // Payments

                $payments = $paymentDetails['payment_info'];

                foreach ($payments as $payment) {
                    Payment::create(
                        [
                            'order_id'    => $order['id'],
                            'method'      => $payment['method'], 
                            'amount'      => $payment['amount'],
                        ]
                    );
                }

                // Fee

                $fees   = $paymentDetails['sales_fee'];
                $fees[] = $mercadoLivre->getShippingCost($orders[$order['order_id']]['shipping_id']);

                foreach ($fees as $fee) {
                    Fee::create(
                        [
                            'order_id'    => $order['id'],
                            'description' => $fee['description'], 
                            'amount'      => $fee['amount'],
                        ]
                    );
                }

                // Change need_update_flag
                Order::where('id', $order['id'])
                    ->update(['need_update_flag' => 0]);


            }

            // Enviar para o Bling
        } else {
            dd("Não tem pedidos para processar");
        }
    }
}
