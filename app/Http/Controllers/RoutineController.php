<?php
namespace App\Http\Controllers;

use Exception;
use DateTime;
use App\Classes\Parser;
use App\Models\Buyer;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class RoutineController extends Controller
{
    public $limit = 50;
    public $termExtensionInDays = 28;

    public function getOrdersMercadoLivre($offset = null)
    {
        $request_data = array(
            'seller' => env('MERCADOLIVRE_SELLER_ID'),
            'limit'  => $this->limit,
            'sort'   => 'date_desc',
            //'order.status' => 'paid',
        );

        if (env('TEST_MODE')) {
            Log::info(
                "[getOrders]: [Test Mode On] ORDER_ID: " . env('ORDER_ID')
            );
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

            Log::info(
                "[getOrders]: Status: " . $response->status() .
                " - Body: " . $response->body()
            );

            return $response->json()['results'];

        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            dd("[getOrders]: " . $e->getMessage());
            return null;
        }
    }

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
                        'payment_date'     => $date->format('Y-m-d H:i:s'),
                        'need_update_flag' => 1,
                        'bling_send_flag'  => 0,
                    ]
                );

                $orderCreated = Order::where('order_id', $order['order_id'])->first();

                $payments_ids = $order['payments_ids'];

                foreach ($payments_ids as $payment_id) {
                    Payment::create(
                        [
                            'order_id'    => $orderCreated['id'],
                            'payment_id'  => $payment_id,
                        ]
                    );
                }

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

            if ($response->status() != 200) {
                Log::warning(
                    "[blingContaAPagar]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }
            Log::info("Conta a pagar enviada para Bling: " . $xml);
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

            if ($response->status() != 200) {
                Log::warning("[blingContaAReceber]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            Log::info("Conta a receber enviada para Bling: " . $xml);
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
                    "[blingBaixaContaAPagar]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }
            Log::info("Baixa confirmada de conta a pagar registrada no Bling: " . $xml);
            return $response->json();
        }catch(Exception $e){
            Log::error("[blingBaixaContaAPagar]: " . $e->getMessage());
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
                    "[blingBaixaContaAReceber]: Status: " . $response->status() .
                    " - Body: " . $response->body()
                );
                return null;
            }
            Log::info("Baixa confirmada de conta a receber registrada no Bling: " . $xml);
            return $response->json();
        }catch(Exception $e){
            Log::error("[blingBaixaContaAReceber]: " . $e->getMessage());
            return null;
        }
    }

    public function updateOrdersData($quantity = null)
    {
        if ($quantity == null) { $quantity = 1;}

        //Preenche dados que faltam
        $orders_registered = Order::where('need_update_flag', true)
            ->take($quantity)->get();

        $mercadoLivre = new Mercadolivre();

        foreach ($orders_registered as $order) {

            //Pegar Nota Fiscal
            $invoice_number = $mercadoLivre->getInvoice($order['order_id']);

            if ($invoice_number) {
                Order::where('id', $order['id'])
                    ->update(['invoice' => $invoice_number]);
            }

            $payments = Payment::where('order_id', $order['id'])->get();

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
                if ($fee['amount'] != 0) {
                    Fee::create(
                        [
                            'order_id'    => $order['id'],
                            'description' => $fee['description'],
                            'amount'      => $fee['amount'],
                        ]
                    );
                }
            }

            // Change need_update_flag
            Order::where('id', $order['id'])
                ->update(['need_update_flag' => 0]);

        }
    }

    public function registerOrdersBling($quantity = null)
    {
        // Enviar para o Bling
        $orders_not_send = Order::where('bling_send_flag', false)
            ->where('need_update_flag', false)
            ->take(2)->get();

        foreach ($orders_not_send as $order) {
            // Contas a pagar
            $contasAPagar = array();
            //dd($order);
            foreach ($order->fees as $fee) {
                //dd($order);
                $date = new DateTime($order['payment_date']);
                $dataWithTermExtension = date('d/m/Y', strtotime("+". $this->termExtensionInDays ." days",strtotime($order['payment_date'])));

                $nroDocumento = ($order['invoice']) ? $order['invoice']."/01" : $order['order_id'];

                //Histórico
                $historico = "Numero do Pedido: " . $order['order_id'] . " | Descrição: " . $fee['description'];
                if ($order['invoice']) {
                    $historico = $historico . " | Nota Fiscal: " . $order['invoice'];
                } else {
                    $historico = $historico . " | Nota Fiscal: Não foi emitida";
                }

                $conta = array(
                        "dataEmissão"        => $date->format('d/m/Y'),
                        "vencimentoOriginal" => $dataWithTermExtension,
                        "competencia"        => $date->format('d/m/Y'),
                        "nroDocumento"       => $nroDocumento,
                        "valor"              => $fee['amount'], //obrigatorio
                        "histórico"          => $historico,
                        "categoria"          => "4.1.01.06.11 Correios e malotes",
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
                    /* if ($response) {
                        $contaAPagarID = $response['retorno']['contapagar'][0]['contapagar']['id'];

                        $dataLiquidacao = new DateTime('NOW');

                        $xmlBaixa = array(
                            "contapagar" => array(
                                "dataLiquidacao" => $dataLiquidacao->format('d/m/Y'),
                                "juros"          => "",
                                "desconto"       => "",
                                "acrescimo"      => "",
                                "tarifa"         => ""
                            )
                        );

                        $parser = new Parser();
                        $xmlBaixa = $parser->arrayToXml(
                            $xmlBaixa,
                            "<contapagar/>"
                        );

                        $this->blingBaixaContaAPagar($contaAPagarID, $xmlBaixa);
                    } */
                }
            }

            $contaAReceberAmount = 0;

            $historico = "Ref. ao pedido de venda nº " . $order['invoice'] .
                        " | Método de pagamento:";

            foreach ($order->payments as $payment) {
                $historico .= " ".$payment['method'];
                $contaAReceberAmount += $payment['amount'];
            }

            $date = new DateTime($order['payment_date']);
            $dataWithTermExtension = date(
                'd/m/Y',
                strtotime("+". $this->termExtensionInDays ." days", strtotime($order['payment_date']))
            );
            $nroDocumento = ($order['invoice']) ? $order['invoice']."/01" : $order['order_id'];

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
                $this->blingContaAReceber($xml);
                // Dar baixar contas a receber
                if ($response) {
                    $contaAReceberID = $response['retorno']['contasreceber'][0]['contareceber']['id'];

                    $dataLiquidacao = new DateTime('NOW');

                    $xmlBaixa = array(
                        "contasreceber" => array(
                            "dataLiquidacao" => $dataLiquidacao->format('d/m/Y'),
                            "juros"          => "",
                            "desconto"       => "",
                            "acrescimo"      => "",
                            "tarifa"         => ""
                        )
                    );

                    $parser = new Parser();
                    $xmlBaixa = $parser->arrayToXml($xmlBaixa, "<contasreceber/>");

                    $this->blingBaixaContaAReceber($contaAReceberID, $xmlBaixa);

                }
            }


            // Change bling_send_flag
            if (env('SEND_TO_BLING')) {
                Order::where('id', $order['id'])
                    ->update(['bling_send_flag' => true]);
            }
        }
    }

    public function getOrders(Request $request)
    {
        $o = array();

        $offset = 0;

        //$date = date('Y-m-d');//"2022-03-21";

        $stopFlag = false;

        while (!$stopFlag) {

            $orders = $this->getOrdersMercadoLivre($offset);
            //dd($orders);
            foreach ($orders as $order) {

                $payments_ids = [];

                foreach ($order['payments'] as $payment) {
                    array_push($payments_ids, $payment['id']);
                }

                $i = [
                    'order_id'     => $order['id'],
                    'created_in'   => $order['date_created'],
                    'buyer'        => $order['buyer']['nickname'],
                    'shipping_id'  => $order['shipping']['id'],
                    'payments_ids' => $payments_ids,
                ];

                Log::info("Adding new order - Order ID: " . $order['id']);
                $o[$i['order_id']] = $i;

            }

            if ($offset < 2) {
                Log::info("Offset inside limit. Offset: " . $offset);
            } else {
                Log::info("Changing flag to true. Offset: " . $offset);
                $stopFlag = true;
                break;
            }
            Log::info("Offset: " . $offset);
            $offset++;
        }

        $orders = $o;
        //dd($orders);
        if ($orders) {
            $this->registerOrdersLocal($orders);
        } else {
            Log::info("No orders to register locally");
        }
    }
}
