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

            if ($response->status() != 200) {
                Log::warning("[getPayer]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $response->json();
        }catch(Exception $e){
            Log::error("[getPayer]: " . $e->getMessage());
            return null;
        }
    }

    public function getOrder($orderId)
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => 1,
            'sort'   => 'date_desc',
            'q'      => $orderId,
        );

        try{
            $url = env("MERCADOLIVRE_API_URL") . "/orders/search";

            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))
                        ->get($url, $request_data);

            if ($response->status() != 200) {
                Log::warning(
                    "[getOrders]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }

            /* Log::info(
                "[getOrders]: Status: " . $response->status() .
                " - Body: " . $response->body()
            ); */

            return $response->json()['results'];

        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            return null;
        }
    }

    public function getOrders($offset = null)
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => 50,
            'sort'   => 'date_desc',
            //'order.status' => 'paid',
        );

        if (env('TEST_MODE')) {
            Log::info("[getOrders]: [Test Mode On] ORDER_ID: " . env('ORDER_ID'));
            $request_data['q'] = env('ORDER_ID');
        }


        if ($offset) {
            $request_data['offset'] = $offset;
        }

        try{
            $url = env("MERCADOLIVRE_API_URL") . "/orders/search";

            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))
                        ->get($url, $request_data);

            if ($response->status() != 200) {
                Log::warning(
                    "[getOrders]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }

            /* Log::info(
                "[getOrders]: Status: " . $response->status() .
                " - Body: " . $response->body()
            ); */

            return $response->json()['results'];

        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            return null;
        }
    }

    public function getOrdersByDate($date)
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => 50,
            //'sort'   => 'date_asc',
            'order.status' => 'paid',
            'order.date_created.from' => $date['from'],
            'order.date_created.to' => $date['to'],
        );

        try{
            $offset = 0;
            $results = [];
            $url = env("MERCADOLIVRE_API_URL") . "/orders/search";
            $total = 2;

            while($offset <= $total) {
                Log::info("[getOrdersByDate] Offset: $offset - Total: $total");
                $request_data['offset'] = $offset;
                Log::info("request_data:" . json_encode($request_data));
                $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url, $request_data);
                $results = array_merge($results, $response->json()['results']);
                //$total = $response->json()['paging']['total'];
                $offset++;
            }

            return $results;

        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            return $results;
        }
    }

    /* public function getShippingCostById($shippingId)
    {
        try{

            $url = env("MERCADOLIVRE_API_URL") . "/shipments/" . $shippingId;
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url);

            if ($response->status() != 200) {
                Log::warning(
                    "[getShippingCost]: Shipping ID: $shippingId" .
                    " - Status: " . $response->status() .
                    " - Body: " . $response
                );
                return null;
            }
            return array(
                'description' => 'Envio pelo Mercado Envios',
                'amount'      => $response['shipping_option']['list_cost'],
            );
        } catch (Exception $e) {
            Log::error("[getShippingCost]: " . $e->getMessage());
            dd("[getShippingCost]: " . $e->getMessage());
            return null;
        }
    } */

    public function getShippingDetails($orderId)
    {
        try{

            $url = env("MERCADOLIVRE_API_URL") . "/orders/" . $orderId . "/shipments";
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url);

            if ($response->status() != 200) {
                Log::info(
                    "[getShippingDetails]: Order ID: " . $orderId .
                    " - Status: " . $response->status() .
                    " - Body: " . json_encode($response->json())
                );
                return null;
            }
            return $response;
        } catch (Exception $e) {
            Log::error("[getShippingDetails]: " . $e->getMessage());
            return null;
        }
    }

    public function getShippingCost($orderId)
    {
        try{

            $url = env("MERCADOLIVRE_API_URL") . "/orders/" . $orderId . "/shipments";
            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url);

            if ($response->status() != 200) {
                Log::info(
                    "[getShippingCost]: Order ID: " . $orderId .
                    " - Status: " . $response->status() .
                    " - Body: " . json_encode($response->json())
                );
                return null;
            }
            return array(
                'description'   => 'Envio pelo Mercado Envios',
                'amount'        => $response['shipping_option']['list_cost'],
                'base_cost'     => $response['base_cost'],
                'shipping_cost' => $response['shipping_option']['cost'],
                'logistic_type' => $response['logistic_type'],
            );
        } catch (Exception $e) {
            Log::error("[getShippingCost]: " . $e->getMessage());
            return null;
        }
    }

    public function getPaymentDetails($payment)
    {

        try{
            $url = env("MERCADOPAGO_API_URL") . "/v1/payments/" . $payment['payment_id'];

            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url);

            if ($response->status() != 200) {
                $log =  "[getPaymentDetails]:PaymentId: " . $payment['payment_id'] .
                        " Status: " . $response->status() .
                        " - Body: " . $response->body();
                Log::warning($log);
                return null;
            }

            return $response = array(
                        'method' => $response['payment_method_id'],
                        'amount' => $response['transaction_amount'],
                    );

        }catch(Exception $e){
            $log = "[getPaymentDetails]: " . $e->getMessage();
            Log::error($log);
            return null;
        }
    }

    public function getPaymentsDetailsFroFront($payments)
    {
        $paymentResponse = array();
        $paymentResponse['sales_fee'] = array();
        $paymentResponse['payment_info'] = array();

        foreach ($payments as $payment) {
            try{
                $url = env("MERCADOPAGO_API_URL") . "/v1/payments/" . $payment['id'];
                //Log::info($url);
                $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))
                            ->get($url);

                if ($response->status() != 200) {
                    $log =  "[getPaymentDetails]:PaymentId: " . $payment['id'] .
                            " Status: " . $response->status() .
                            " - Body: " . json_encode($response->body());
                    Log::warning($log);
                    return null;
                }

                //Log::info($response->body());

                $response = array(
                    'sales_fee'     => $this->responseFeeHandler($response->json()),
                    'payer'         => $response->json()['payer'],
                    'payment_info'  =>  array(
                        array(
                            'method'        => $response['payment_method_id'],
                            'amount'        => $response['transaction_amount'],
                            'date_approved' => $response['date_approved']
                        ),
                    ),
                );

            }catch(Exception $e){
                $log = "[getPaymentDetails]: " . $e->getMessage();
                Log::error($log);
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

            $paymentResponse['payment_info'] = $payment_info;

            $paymentResponse['payer'] = $response['payer'];

        }

        return $paymentResponse;

    }

    public function getPaymentsDetails($payments)
    {
        $paymentResponse = array();
        $paymentResponse['sales_fee'] = array();
        $paymentResponse['payment_info'] = array();

        foreach ($payments as $payment) {
            try{
                $url = env("MERCADOPAGO_API_URL") . "/v1/payments/" . $payment['payment_id'];
                //Log::info($url);
                $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))->get($url);

                if ($response->status() != 200) {
                    $log =  "[getPaymentDetails]:PaymentId: " . $payment['payment_id'] .
                            " Status: " . $response->status() .
                            " - Body: " . $response->body();
                    Log::warning($log);
                    return null;
                }

                //Log::info($response->body());

                $response = array(
                    'sales_fee'     => $this->responseFeeHandler($response->json()),
                    'payer'         => $response->json()['payer'],
                    'payment_info'  =>  array(
                        array(
                            'method'        => $response['payment_method_id'],
                            'amount'        => $response['transaction_amount'],
                            'date_approved' => $response['date_approved']
                        ),
                    ),
                );

            }catch(Exception $e){
                $log = "[getPaymentDetails]: " . $e->getMessage();
                Log::error($log);
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

            $paymentResponse['payment_info'] = $payment_info;

            $paymentResponse['payer'] = $response['payer'];

        }

        return $paymentResponse;

    }

    public function responseFeeHandler($responseFeeDetails)
    {
        $feeDetails = array();

        $typeDict = array(
            'ml_fee' => 'Gest??o de Vendas',
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
                    Log::error(
                        "[responseFeeHandler]: " . $e->getMessage() .
                        " - [Data]: " . $fee
                    );
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
                if($response->status() == 404) {
                    Log::info("[getInvoice]: Order ID: $orderId - Status: " . $response->status() . " - Body: " . $response->body());
                } else {
                    Log::error("[getInvoice]: Order ID: $orderId - Status: " . $response->status() . " - Body: " . $response->body());
                }
                return null;
            }
            return $response->json()['invoice_number'];
        }catch(Exception $e){
            Log::error("[getInvoice]: Order ID: $orderId - [Error]" . $e->getMessage());
            return null;
        }
    }

    public function getOrdersForFront(Request $request)
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => 1,
            'sort'   => 'date_desc',
            'order.status' => 'paid',
        );

        if (env('TEST_MODE')) {
            Log::info(
                "[getOrders]: [Test Mode On] ORDER_ID: " . env('ORDER_ID')
            );
            $request_data['q'] = env('ORDER_ID');
        }


        if ($request->offset) {
            $request_data['offset'] = $request->offset;
        }

        if ($request->status) {
            $request_data['order.status'] = "cancelled";
        }


        try{
            $url = env("MERCADOLIVRE_API_URL") . "/orders/search";

            $response = Http::withToken(env('MERCADOPAGO_ACCESS_TOKEN'))
                        ->get($url, $request_data);

            //dd($response->body());

            if ($response->status() != 200) {
                Log::info(
                    "[getOrdersForFront]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }

            /* Log::info(
                "[getOrdersForFront]: Status: " . $response->status() .
                " - Body: " . $response->body()
            ); */

            return Response($this->old_responseOrdersHandler($response->json()), 200);

        }catch(Exception $e){
            Log::error("[getOrdersForFront]: " . $e->getMessage());
            dd("[getOrdersForFront]: " . $e->getMessage());
            return null;
        }
    }

    public function old_responseOrdersHandler($responseOrders)
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

            $paymentDetails = $this->getPaymentsDetailsFroFront($payments);

            if ($paymentDetails['payer']['first_name'] == "Splitter") {
                unset($paymentDetails['payer']);
            } else {
                $payer = $paymentDetails['payer'];
                $input['buyer']['full_name']      = $payer['first_name'] . " "  . $payer['last_name'];
                $input['buyer']['email']          = (isset($payer['email'])) ? $payer['email'] : "";
                $input['buyer']['identification'] = (isset($payer['identification'])) ? $payer['identification'] : "";
                $input['buyer']['phone']          = (isset($payer['phone'])) ? $payer['phone'] : "";
                unset($paymentDetails['payer']);
            }

            $input = array_merge($input, $paymentDetails);

            $responseShippingCost = $this->getShippingCost($order['id']);
            if ($responseShippingCost) { $input['sales_fee'][] = $responseShippingCost; }
            /* Log::info(
                "OrderID: " . $input['order_id'] .
                " - Fees: " . json_encode($input['sales_fee']) .
                " - Payments: " . json_encode($input['payment_info'])
            ); */
            $orders[] = $input;
        }
        return $orders;
    }

    public function responseOrdersHandler($responseOrders)
    {
        $orders = array();

        foreach ($responseOrders as $order) {

            $payments_ids = [];

            foreach ($order['payments'] as $payment) {
                if ($payment['status'] == "approved") {
                    array_push($payments_ids, $payment['id']);
                }
            }

            $i = [
                'order_id'     => $order['id'],
                'created_in'   => $order['date_created'],
                'buyer'        => $order['buyer']['nickname'],
                'shipping_id'  => $order['shipping']['id'],
                'payments_ids' => $payments_ids,
            ];

            Log::info("Adding new order to array - Order ID: " . $order['id']);
            $orders[$i['order_id']] = $i;

        }

        return $orders;
    }
}
