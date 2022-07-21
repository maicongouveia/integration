<?php
namespace App\Http\Controllers;

use Exception;
use DateTime;
use App\Classes\Parser;
use App\Jobs\GetNewOrdersJob;
use App\Jobs\ProcessJobs;
use App\Models\Buyer;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Integration extends Controller
{
    public $termExtensionInDays = 28;

    public function registerOrdersLocal($orders)
    {
        //Cadastra pedidos que ainda não estão na base
        foreach ($orders as $order) {
            $exists = Order::where('order_id', $order['order_id'])->first();

            if (!$exists) {
                $date = new DateTime($order['created_in']);
                Order::create(
                    [
                        'order_id'         => $order['order_id'],
                        'invoice'          => null,
                        'created_in'       => $date,
                        'need_update_flag' => 1,
                        'bling_send_flag'  => 0,
                        'status'           => "PENDING",
                    ]
                );


                $orderCreated = Order::where('order_id', $order['order_id'])->first();

                Buyer::create(
                    [
                        'order_id' => $orderCreated['id'],
                        'name'     => $order['buyer'],
                    ]
                );

                $payments_ids = $order['payments_ids'];

                foreach ($payments_ids as $payment_id) {
                    Log::info('[order_id: ' . $orderCreated['id'] . ' - payment_id: '.$payment_id.']');
                    $exists = DB::table('payment')->where('payment_id', $payment_id)->first();
                    if(!$exists){
                        Payment::create(
                            [
                                'order_id'    => $orderCreated['id'],
                                'payment_id'  => $payment_id,
                                'send_baixa_to_bling' => 0,
                            ]
                        );
                    }
                }

            } else {
                Log::info("[registerOrdersLocal] Order exists - " . $order['order_id']);
            }
        }
    }

    public function blingContaAPagar($xml)
    {
        $request_data = array(
            'apikey' => env('BLING_API_KEY'),
            'xml'    => $xml,
        );
        //dd($request_data);
        try{
            $response = Http::asForm()->post('https://bling.com.br/b/Api/v2/contapagar/json/', $request_data);

            if ($response->status() != 201) {
                Log::info(
                    "[blingContaAPagar]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }
            //Log::info("[blingContaAPagar]: Conta a pagar enviada para Bling: " . $xml);
            Log::info(
                "[blingContaAPagar]: Status: " . $response->status() .
                " - Body: " . $response->body()
            );
            return $response->json();
        }catch(Exception $e){
            Log::error("[blingContaAPagar]: " . $e->getMessage());
            return null;
        }
    }

    public function blingContaAReceber($xml)
    {
        $request_data = array(
            'apikey' => env('BLING_API_KEY'),
            'xml'    => $xml,
        );
        //dd($request_data);
        try{
            $response = Http::asForm()->post('https://bling.com.br/b/Api/v2/contareceber/json/', $request_data);

            if ($response->status() != 201) {
                Log::info("[blingContaAReceber]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            //Log::info("[blingContaAReceber]: Conta a receber enviada para Bling: " . $xml);
            Log::info("[blingContaAReceber]: Status: " . $response->status() . " - Body: " . $response->body());
            return $response->json();
        }catch(Exception $e){
            Log::error("[blingContaAReceber]: " . $e->getMessage());
            return null;
        }
    }

    public function blingBaixaContaAPagar($id, $xml)
    {
        $request_data = array(
            'apikey' => env('BLING_API_KEY'),
            'xml'    => $xml,
        );
        //dd($request_data);
        try{
            $response = Http::asForm()->put('https://bling.com.br/b/Api/v2/contapagar/' . $id, $request_data);

            if ($response->status() != 200) {
                Log::warning(
                    "[blingBaixaContaAPagar]: ID: $id - Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }
            Log::info("[blingBaixaContaAPagar][Baixa confirmada]: ID: $id - [XML] " . $xml . " [Response] " . json_encode($response->json()));
            return true;
        }catch(Exception $e){
            Log::error("[blingBaixaContaAPagar]: ID: $id - [Error] " . $e->getMessage());
            return null;
        }
    }

    public function blingBaixaContaAReceber($id, $xml)
    {
        $request_data = array(
            'apikey' => env('BLING_API_KEY'),
            'xml'    => $xml,
        );
        //dd($request_data);
        try{
            $response = Http::asForm()->put('https://bling.com.br/b/Api/v2/contareceber/' . $id, $request_data);

            if ($response->status() != 200) {
                Log::warning(
                    "[blingBaixaContaAReceber]: ID: $id - Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }
            Log::info("[blingBaixaContaAReceber][Baixa confirmada]: ID: $id - [XML] " . $xml . " [Response] " . json_encode($response->json()));
            return true;
        }catch(Exception $e){
            Log::error("[blingBaixaContaAReceber]: ID: $id [Error] " . $e->getMessage());
            return null;
        }
    }

    public function registerBuyer($buyer, $order_id)
    {
        //Buyer
        if($buyer['first_name']){
            if ($buyer['first_name'] != "Splitter") {

                $payer = $buyer;

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

                Buyer::where('order_id', $order_id)->update([
                    'name'                  => $payer['first_name'] . " "  . $payer['last_name'],
                    'email'                 => $payer['email'],
                    'identificationType'    => $payer['identification']['type'],
                    'identificationNumber'  => $payer['identification']['number'],
                    'phone'                 => $phone,
                ]);
            }
        }
    }

    public function enrichOrders($quantity = null)
    {
        if ($quantity == null) { $quantity = 1;}

        $tenMinutesLaterDate = now()->addMinutes(10);

        //Preenche dados que faltam
        $orders_registered = Order::where('need_update_flag', true)
            ->where('created_in', '<', $tenMinutesLaterDate)
            ->take($quantity)->get();

        $mercadoLivre = new Mercadolivre();

        foreach ($orders_registered as $order) {

            //Pegar Nota Fiscal
            if($order['invoice']) {
                $invoice_number = $mercadoLivre->getInvoice($order['order_id']);

                if ($invoice_number) {
                    Order::where('id', $order['id'])
                        ->update(['invoice' => $invoice_number]);
                }
            }

            $payments = Payment::where('order_id', $order['id'])->get();

            foreach ($payments as $payment) {

                $paymentDetail = $mercadoLivre->getPaymentDetails($payment);

                if($paymentDetail){
                    $date = new DateTime($payment['date_approved']);
                    Payment::where('payment_id',$payment['payment_id'])
                        ->update(
                        [
                            'method'       => $paymentDetail['method'],
                            'amount'       => $paymentDetail['amount'],
                            'payment_date' => $date->format('Y-m-d H:i:s')
                        ]);
                } else {
                    throw new Exception("[enrichPayment]: Payment ID: " . $payment['payment_id']);
                }

            }

            $paymentDetails = $mercadoLivre->getPaymentsDetails($payments);

            // Fee
            $fees = $paymentDetails['sales_fee'];

            $shippingInfo = $mercadoLivre->getShippingCost($order['order_id']);

            if ($shippingInfo) {

                if ($shippingInfo['logistic_type'] == 'self_service') {
                    $amount = $shippingInfo['base_cost'] - $shippingInfo['amount'];

                    if($amount > 0) {
                        DB::table('shipping_refund')->insert([
                            'order_id' => $order['id'],
                            'bling_id' => null,
                            'amount'   => $amount,
                        ]);
                    }
                }

                /*
                $shippingInfo['amount'] = $shippingInfo['shipping_cost'] - $shippingInfo['amount'];

                if ( $shippingInfo['amount'] < 0 ) { $shippingInfo['amount'] = $shippingInfo['amount'] * -1;}
                */

                if ($shippingInfo['logistic_type'] != 'self_service') {
                    $fees[] = $shippingInfo;
                }
            }

            foreach ($fees as $fee) {
                if ($fee['amount'] != 0) {
                    $exists = DB::table('fee')->where('order_id', $order['id'])->where('description', $fee['description'])->where('amount', $fee['amount'])->first();
                    if(!$exists){
                        Fee::create(
                            [
                                'order_id'    => $order['id'],
                                'description' => $fee['description'],
                                'amount'      => $fee['amount'],
                                'send_baixa_to_bling' => 0,
                            ]
                        );
                    }
                }
            }

            if(array_key_exists('payer',$paymentDetails)){
                $this->registerBuyer($paymentDetails['payer'], $order['id']);
            }

            // Change need_update_flag
            Order::where('id', $order['id'])
                ->update(['need_update_flag' => 0]);

        }
    }

    public function categoryHandler($description)
    {
        $categoriaDict = array(
            'Gestão de Vendas'          => "4.1.01.06.12 Gestão de Vendas",
            'Tarifa de Venda'           => "4.1.01.06.13 Tarifa de Venda",
            'Envio pelo Mercado Envios' => "4.1.01.07.06 Custo de envio por Fulfillment",
        );

        if(! array_key_exists($description, $categoriaDict)) {
            return $description;
        }

        return $categoriaDict[$description];

    }

    public function sumDeliveryTax($amount, $orderId)
    {
        $mercadoLivre = new Mercadolivre();
        $shippingInfo = $mercadoLivre->getShippingCost($orderId);

        if($shippingInfo){ $amount += $shippingInfo['shipping_cost']; }

        return $amount;
    }

    public function registerOrdersBling($quantity = null)
    {
        if ($quantity == null) { $quantity = 1;}

        // Enviar para o Bling
        $orders_not_send = Order::where('bling_send_flag', false)
            ->where('need_update_flag', false)
            ->take($quantity)->get();

        foreach ($orders_not_send as $order) {
            $nroDocumento = ($order['invoice']) ? $order['invoice']."/01" : $order['order_id'];
            // Contas a pagar
            //dd($order);
            foreach ($order->fees as $fee) {
                if($fee->bling_id) {continue;}

                $date = new DateTime($order['created_in']);
                $dataWithTermExtension = date('d/m/Y', strtotime("+". $this->termExtensionInDays ." days",strtotime($order['created_in'])));

                //Histórico
                $historico = "Numero do Pedido: " . $order['order_id'] . " | Descrição: " . $fee['description'];
                if ($order['invoice']) {
                    $historico = $historico . " | Nota Fiscal: " . $order['invoice'];
                } else {
                    $historico = $historico . " | Nota Fiscal: Não foi emitida";
                }

                $categoria = $this->categoryHandler($fee['description']);

                $conta = array(
                        "dataEmissão"        => $date->format('d/m/Y'),
                        "vencimentoOriginal" => $dataWithTermExtension,
                        "competencia"        => $date->format('d/m/Y'),
                        "nroDocumento"       => $nroDocumento,
                        "valor"              => $fee['amount'], //obrigatorio
                        "historico"          => $historico,
                        "categoria"          => $categoria,
                        "portador"           => "1.1.01.02.03 MercadoPago ",
                        "idFormaPagamento"   => 1430675,
                        "ocorrencia"         => array(//obrigatorio
                                                        "ocorrenciaTipo"      => "U", //obrigatorio
                                                        "diaVencimento"       => "",
                                                        "nroParcelas"         => "",
                                                        "diaSemanaVencimento" => "",
                        ),
                        "fornecedor"         => array(//obrigatorio
                                                        "nome"        => $order->buyer['name'],//obrigatorio
                                                        "id"          => "",
                                                        "cpf_cnpj"    => $order->buyer['identificationNumber'],
                                                        "tipoPessoa"  => "",
                                                        "ie_rg"       => "",
                                                        "endereco"    => "",
                                                        "numero"      => "",
                                                        "complemento" => "",
                                                        "cidade"      => "",
                                                        "bairro"      => "",
                                                        "cep"         => "",
                                                        "uf"          => "",
                                                        "email"       => $order->buyer['email'],
                                                        "fone"        => $order->buyer['phone'],
                                                        "celular"     => $order->buyer['phone'],
                        ),
                );

                $parser = new Parser();
                $xml = $parser->arrayToXml($conta, "<contapagar/>");
                //dd($xml);
                if (env('SEND_TO_BLING')) {
                    $response = $this->blingContaAPagar($xml);
                    // Dar baixar contas a pagar
                    if ($response) {
                        $contaAPagarID = $response['retorno']['contaspagar'][0]['contapagar']['id'];
                        Fee::where('id', $fee['id'])->update(['bling_id' => $contaAPagarID]);
                    }
                } else {
                    Log::info("[SEND_TO_BLING][OFF] - [CONTA A PAGAR] - Order ID: " . $fee['order_id'] . " - ID: " . $fee['id'] . " - " . $fee['description'] . ' - ' . $fee['amount']);
                }
            }

            //Send shipping refund

            $refund_shipping = DB::table('shipping_refund')->where('order_id', $order->id)->take(1)->get();

            if ($refund_shipping->count() > 0) {
                $refund_shipping  = $refund_shipping[0];

                $date = new DateTime($order['created_in']);
                $dataWithTermExtension = date(
                    'd/m/Y',
                    strtotime("+". $this->termExtensionInDays ." days", strtotime($order['created_in']))
                );

                //Histórico
                $historico = "Numero do Pedido: " . $order['order_id'];
                if ($order['invoice']) {
                    $historico = $historico . " | Nota Fiscal: " . $order['invoice']."/01";
                } else {
                    $historico = $historico . " | Nota Fiscal: Não foi emitida";
                }

                $historico = $historico . " | Reembolso do Frete";

                $contaAReceber = array(
                    "dataEmissao"  => $date->format('d/m/Y'),
                    "vencimentoOriginal" => $dataWithTermExtension,
                    "competencia"  => $date->format('d/m/Y'),
                    //"nroDocumento" => $nroDocumento,
                    "valor"        => $refund_shipping->amount, //obrigatorio
                    "historico"    => $historico,
                    "categoria"    => "3.1.01.01.08 ABONO POR SUBSIDIO FLEX",
                    "idFormaPagamento" => "1430675",
                    "portador"   => "1.1.01.02.03 MercadoPago ",
                    "vendedor"   => "Mercado Livre Full",
                    "ocorrencia" => array(//obrigatorio
                        "ocorrenciaTipo" => "U",//obrigatorio
                        "diaVencimento"  => "",
                        "nroParcelas"    => ""
                    ),
                    "cliente" => array(//obrigatorio
                        "nome"     => $order->buyer['name'],//obrigatorio
                        "cpf_cnpj" => $order->buyer['identificationNumber'],
                        "email"    => $order->buyer['email'],
                    ),
                );

                $parser = new Parser();
                $xml = $parser->arrayToXml($contaAReceber, "<contareceber/>");
                //dd($xml);
                if (env('SEND_TO_BLING')) {
                    $response = $this->blingContaAReceber($xml);

                    if ($response) {
                        $contaAReceberID = $response['retorno']['contasreceber'][0]['contaReceber']['id'];

                        DB::table('shipping_refund')->where('id', $refund_shipping->id)
                            ->update(['bling_id' => $contaAReceberID]);
                    }
                } else {
                    Log::info("[SEND_TO_BLING][OFF] - [shipping_refund] - " . $refund_shipping->amount);
                }

            }

            //Send Conta a Receber

            //Histórico
            $historico = "Numero do Pedido: " . $order['order_id'];
            if ($order['invoice']) {
                $historico = $historico . " | Nota Fiscal: " . $order['invoice']."/01";
            } else {
                $historico = $historico . " | Nota Fiscal: Não foi emitida";
            }

            $historico = $historico . " | Método de pagamento:";

            $contaAReceberAmount = 0;

            foreach ($order->payments as $payment) {
                $historico .= " ".$payment['method'];
                $contaAReceberAmount += $payment['amount'];
            }

            $contaAReceberAmount = $this->sumDeliveryTax($contaAReceberAmount, $order['order_id']);

            $date = new DateTime($order['created_in']);
            $dataWithTermExtension = date(
                'd/m/Y',
                strtotime("+". $this->termExtensionInDays ." days", strtotime($order['created_in']))
            );

            $contaAReceber = array(
                "dataEmissao"  => $date->format('d/m/Y'),
                "vencimentoOriginal" => $dataWithTermExtension,
                "competencia"  => $date->format('d/m/Y'),
                "nroDocumento" => $nroDocumento,
                "valor"        => $contaAReceberAmount, //obrigatorio
                "historico"    => $historico,
                "categoria"    => "3.1.01.01.02 Revenda Mercadoria (Terceiros)",
                "idFormaPagamento" => "1430675",
                "portador"   => "1.1.01.02.03 MercadoPago ",
                "vendedor"   => "Mercado Livre Full",
                "ocorrencia" => array(//obrigatorio
                    "ocorrenciaTipo" => "U",//obrigatorio
                    "diaVencimento"  => "",
                    "nroParcelas"    => ""
                ),
                "cliente" => array(//obrigatorio
                    "nome"     => $order->buyer['name'],//obrigatorio
                    "cpf_cnpj" => $order->buyer['identificationNumber'],
                    "email"    => $order->buyer['email'],
                ),
            );

            $parser = new Parser();
            $xml = $parser->arrayToXml($contaAReceber, "<contareceber/>");
            //dd($xml);
            if (env('SEND_TO_BLING')) {
                if(!$order->payments[0]['bling_id']){
                    $response = $this->blingContaAReceber($xml);
                    // Dar baixar contas a receber
                    if ($response) {
                        $contaAReceberID = $response['retorno']['contasreceber'][0]['contaReceber']['id'];
                        Payment::where('order_id', $order['id'])->update(['bling_id' => $contaAReceberID]);
                    }
                }
            } else {
                $ids = "[";
                foreach($order->payments as $payment) { $ids .= $payment['id'].",";}
                $ids .= "]";

                Log::info("[SEND_TO_BLING][OFF] - [CONTA A RECEBER] - Order ID: " . $order['id'] . " - IDs: $ids - Valor: "  . $contaAReceberAmount);
            }

            // Change bling_send_flag
            if (env('SEND_TO_BLING')) {
                $isAllSendedToBling = true;

                foreach($order->fees as $fee){
                    if(!$fee['bling_id']){
                        $isAllSendedToBling = false;
                    }
                }
                foreach($order->payments as $payment){
                    if(!$payment['bling_id']){
                        $isAllSendedToBling = false;
                    }
                }

                if($isAllSendedToBling){
                    Order::where('id', $order['id'])->update(['bling_send_flag' => true]);
                }
            }
        }
    }

    public function getOrders()
    {
        //GetNewOrdersJob::dispatch();

        $mercadoLivre = new Mercadolivre();
        $responseOrders = $mercadoLivre->getOrders();

        if($responseOrders) {
            $orders = $mercadoLivre->responseOrdersHandler($responseOrders);
            $this->registerOrdersLocal($orders);
        }
        else {
            Log::info("No orders to register locally");
        }
    }

    public function getOrdersToUpdateStatus($quantity)
    {
        $limitDate = now()->subDays(30);
        $orders = DB::table('order')
                        ->where('created_in', '<=', $limitDate)
                        ->where('need_update_flag', 0)
                        ->where('bling_send_flag', 1)
                        ->where('status', 'PENDING');

        if($quantity){
            return $orders->take($quantity)->get();
        }
        return $orders->get();
    }

    public function isOrderPaid($orderId)
    {
        $mercadoLivre = new Mercadolivre();
        $shipmentResponse = $mercadoLivre->getShippingDetails($orderId);
        if ($shipmentResponse) {
            if($shipmentResponse['status'] == "delivered"){
                $statusHistory = $shipmentResponse['status_history'];
                $deliveryDate = Carbon::parse($statusHistory['date_delivered']);
                if (now()->lessThanOrEqualTo($deliveryDate->addDays(5))){
                    return true;
                }
            }
        }
        return false;
    }

    public function updateOrderStatus($order)
    {
        $mercadoLivre = new Mercadolivre();
        $orderResponse = $mercadoLivre->getOrder($order->order_id);

        if(!$orderResponse) { return false;}

        $orderResponse = $orderResponse[0];
        $tags = $orderResponse['tags'];
        $status = $orderResponse['status'];

        if (in_array("paid", $tags) && in_array("delivered", $tags)) {
            if($this->isOrderPaid($order->order_id)){
                DB::table('order')->where('id', $order->id)->update(['status' => "SEND_PAID_TO_BLING"]);
                DB::table('payment')->where('order_id', $order->id)->update(['send_baixa_to_bling' => 1]);
                DB::table('fee')->where('order_id', $order->id)->update(['send_baixa_to_bling' => 1]);
            }
        } else if (in_array("paid", $tags) && $status == "cancelled") {
            DB::table('order')->where('id', $order->id)->update([
                    'status' => "REFUND"
            ]);
        } else if (in_array("not_delivered", $tags) && in_array("not_paid", $tags) && $status == "cancelled") {
            DB::table('order')->where('id', $order->id)->update([
                    'status' => "CANCEL"
            ]);
        }
    }

    public function updateOrdersStatus($orders)
    {
        foreach($orders as $order) {
            $this->updateOrderStatus($order);
        }
    }

    public function getPaidOrdersToSend($quantity)
    {
        $orders = DB::table('order')
                        ->where('need_update_flag', 0)
                        ->where('bling_send_flag', 1)
                        ->where('status', 'SEND_PAID_TO_BLING');

        if ($quantity){
            return $orders->take($quantity)->get();
        }

        return $orders->get();
    }

    public function getPaymentFromOrder($orderId)
    {
        $payments = DB::table('payment')
                    ->where('order_id', $orderId)
                    ->get();

        return $payments;
    }

    public function getFeeFromOrder($orderId)
    {
        $fees = DB::table('fee')
                    ->where('order_id', $orderId)
                    ->get();

        return $fees;
    }

    public function xmlBaixa($tipo)
    {

        //contareceber / contapagar
        $dataLiquidacao = new DateTime('NOW');

        $xmlBaixa = array(
                "dataLiquidacao" => $dataLiquidacao->format('d/m/Y'),
                /* "juros"          => "",
                "desconto"       => "",
                "acrescimo"      => "",
                "tarifa"         => "" */
        );

        $parser = new Parser();
        $xmlBaixa = $parser->arrayToXml($xmlBaixa, "<$tipo/>");
        return $xmlBaixa;
    }

    public function sendBaixaToBling($order)
    {
        $orderId  = $order->id;
        $fees     = $this->getFeeFromOrder($orderId);
        $payments = $this->getPaymentFromOrder($orderId);

        foreach($fees as $fee){
            if($fee->send_baixa_to_bling){
                Log::info("[sendBaixaToBling][CONTA A PAGAR]: Order ID: " . $fee->order_id . ' - Bling ID: ' . $fee->bling_id . " - ID: " . $fee->id);
                $xml = $this->xmlBaixa("contapagar");
                if(env('SEND_TO_BLING')){
                    $response = $this->blingBaixaContaAPagar($fee->bling_id, $xml);
                } else {
                    Log::info("[SEND_TO_BLING: FALSE][BAIXA][CONTA A PAGAR]: Order ID: " . $fee->order_id . ' - Bling ID: ' . $fee->bling_id . " - ID: " . $fee->id);
                }
                if($response){
                    DB::table('fee')->where('id', $fee->id)->update(['send_baixa_to_bling' => '0']);
                    Log::info("[sendBaixaToBling][BAIXA CONFIRMADA][CONTA A PAGAR]: Order ID: " . $fee->order_id . ' - Bling ID: ' . $fee->bling_id . " - ID: " . $fee->id);
                }
            }
        }

        foreach($payments as $payment){
            if($payment->send_baixa_to_bling){
                $xml = $this->xmlBaixa("contareceber");
                Log::info("[sendBaixaToBling][CONTA A RECEBER]: Order ID: " . $payment->order_id . ' - Bling ID: ' . $payment->bling_id . " - ID: " . $payment->id);
                if(env('SEND_TO_BLING')){
                    $response = $this->blingBaixaContaAReceber($payment->bling_id, $xml);
                } else {
                    Log::info("[SEND_TO_BLING: FALSE][BAIXA][CONTA A RECEBER]: Order ID: " . $payment->order_id . ' - Bling ID: ' . $payment->bling_id . " - ID: " . $payment->id);
                }
                if($response){
                    DB::table('payment')->where('id', $payment->id)->update(['send_baixa_to_bling' => '0']);
                    Log::info("[sendBaixaToBling][BAIXA CONFIRMADA][CONTA A RECEBER]: Order ID: " . $payment->order_id . ' - Bling ID: ' . $payment->bling_id . " - ID: " . $payment->id);
                }
            }
        }

        $fees     = $this->getFeeFromOrder($orderId);
        $payments = $this->getPaymentFromOrder($orderId);

        $isAllSendedToBling = true;

        foreach($fees as $fee){
            if($fee->send_baixa_to_bling){
                $isAllSendedToBling = false;
            }
        }
        foreach($payments as $payment){
            if($payment->send_baixa_to_bling){
                $isAllSendedToBling = false;
            }
        }

        if ($isAllSendedToBling){
            DB::table('order')->where('id', $orderId)->update(['status' => 'PAID']);
        }

    }

}
