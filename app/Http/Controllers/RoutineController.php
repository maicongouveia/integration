<?php
namespace App\Http\Controllers;

use App\Classes\Parser;
use App\Models\Buyer;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Payment;
use Exception;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

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
            //'q'      => 5351987526//5332358230//5332358229 //order_id
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
                Log::warning(
                    "[getOrders]: Status: " . $response->status() . 
                    " - Body: " . $response->body()
                );
                return null;
            }
            
            return $response->json()['results'];

        }catch(Exception $e){
            Log::error("[getOrders]: " . $e->getMessage());
            dd("[getOrders]: " . $e->getMessage());
            return null;
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

    public function routine(Request $request)
    {
        $o = array();

        $offset = 0;

        $date = "2022-03-21";//date('Y-m-d');

        $stopFlag = false;

        while (!$stopFlag) {
            
            $orders = $this->getOrders($offset);
            //dd($orders);
            foreach ($orders as $order) {
                         
                $format_date = new DateTime($order['date_created']);
                $order_date = date_format($format_date, 'Y-m-d');
                
                if ($offset < 2) {
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
        //dd($orders);
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
                            'bling_send_flag'  => 0,
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

            // Enviar para o Bling
            $orders_not_send = Order::where('bling_send_flag', false)
                ->take(2)->get();

            foreach ($orders_not_send as $order) {
                // Contas a pagar
                $contasAPagar = array();
                //dd($order);
                foreach ($order->fees as $fee) {
                    $date = new DateTime($order['payment_date']);
                    $nroDocumento = ($order->buyer['identificationNumber']) ? $order->buyer['identificationNumber'] : $order['order_id'];
                    $conta = array(
                            "dataEmissão"        => $date->format('d/m/Y'),
                            "vencimentoOriginal" => $date->format('d/m/Y'),
                            "competencia"        => $date->format('d/m/Y'),
                            "nroDocumento"       => $nroDocumento,
                            "valor"              => $fee['amount'], //obrigatorio
                            "histórico"          => $fee['description'] . " " . $order['order_id'],
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
                    
                    //$this->blingContaAPagar($xml);
                }

                $contaAReceberAmount = 0;

                $historico = "Ref. ao pedido de venda nº " . $order['order_id'] .
                             " | Método de pagamento:";

                foreach ($order->payments as $payment) {
                    $historico .= " ".$payment['method'];
                    $contaAReceberAmount += $payment['amount'];
                }

                $date = new DateTime($order['payment_date']);
                $nroDocumento = ($order->buyer['identificationNumber']) ? $order->buyer['identificationNumber'] : $order['order_id'];

                $contaAReceber = array(                    
                    "dataEmissao"  => $date->format('d/m/Y'),
                    "vencimentoOriginal" => $date->format('d/m/Y'),
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

                //$this->blingContaAReceber($xml);

                // Change need_update_flag
                Order::where('id', $order['id'])
                    ->update(['bling_send_flag' => true]);
            }
            
            

        } else {
            dd("Não tem pedidos para processar");
        }
    }
}
