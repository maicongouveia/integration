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
                    Log:info('[order_id: '.$orderCreated['id'] . ' - payment_id: '.$payment_id.']');
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

    public function registerBuyer($buyer, $order_id)
    {
        //Buyer

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

    public function enrichOrders($quantity = null)
    {
        if ($quantity == null) { $quantity = 1;}

        $tenMinutesLaterDate = date('Y-m-d h:m:s', strtotime("+10 minutes", strtotime('now')));

        //Preenche dados que faltam
        $orders_registered = Order::where('need_update_flag', true)
            ->where('created_in', '<', $tenMinutesLaterDate)
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

            $paymentDetails = $mercadoLivre->getPaymentsDetails($payments);
            //Log::info($paymentDetails);

            // Payments
            $payments = $paymentDetails['payment_info'];

            foreach ($payments as $payment) {
                $date = new DateTime($payment['date_approved']);
                Payment::where('order_id',$order['id'])
                    ->update(
                    [
                        'method'       => $payment['method'],
                        'amount'       => $payment['amount'],
                        'payment_date' => $date->format('Y-m-d H:i:s')
                    ]);
            }

            // Fee
            $fees = $paymentDetails['sales_fee'];

            $shippingInfo = $mercadoLivre->getShippingCost($order['order_id']);

            if ($shippingInfo['base_cost']) {
                $amount = $shippingInfo['base_cost'] - $shippingInfo['amount'];

                if($amount > 0) {
                    DB::table('shipping_refund')->insert([
                        'order_id' => $order['id'],
                        'bling_id' => null,
                        'amount'   => $amount,
                    ]);
                }
            }

            $shippingInfo['amount'] = $shippingInfo['shipping_cost'] - $shippingInfo['amount'];

            if ( $shippingInfo['amount'] < 0 ) { $shippingInfo['amount'] = $shippingInfo['amount'] * -1;}

            $fees[] = $shippingInfo;

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

            $this->registerBuyer($paymentDetails['payer'], $order['id']);

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

    public function sumDeliveryTax($fees, $amount)
    {
        foreach($fees as $fee){
            if ($fee['description'] == "Envio pelo Mercado Envios") {
                $amount += $fee['amount'];
            }
        }
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
            // Contas a pagar
            //dd($order);
            foreach ($order->fees as $fee) {
                //dd($order);
                $date = new DateTime($order['created_in']);
                $dataWithTermExtension = date('d/m/Y', strtotime("+". $this->termExtensionInDays ." days",strtotime($order['created_in'])));

                $nroDocumento = ($order['invoice']) ? $order['invoice']."/01" : $order['order_id'];

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

                        Fee::where('order_id', $order['id'])->update(['bling_id' => $contaAPagarID]);

                     /*
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

                        $this->blingBaixaContaAPagar($contaAPagarID, $xmlBaixa);*/
                    }
                } else {
                    Log::info("[SEND_TO_BLING][OFF] - [CONTA A PAGAR] - " . $fee['description'] . ' - ' . $fee['amount']);
                }
            }

            //Send shipping refund

            $refund_shipping = DB::table('shipping_refund')->where('order_id', $order->id)->take(1)->get();

            if ($refund_shipping) {
                $refund_shipping  = $refund_shipping[0];

                $date = new DateTime($order['created_in']);
                $dataWithTermExtension = date(
                    'd/m/Y',
                    strtotime("+". $this->termExtensionInDays ." days", strtotime($order['created_in']))
                );

                $nroDocumento = ($order['invoice']) ? $order['invoice']."/01" : $order['order_id'];

                $historico = "Ref. ao pedido de venda nº " . $nroDocumento . " | Reembolso do Frete";

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

            $nroDocumento = ($order['invoice']) ? $order['invoice']."/01" : $order['order_id'];

            $historico = "Ref. ao pedido de venda nº " . $nroDocumento . " | Método de pagamento:";

            $contaAReceberAmount = 0;

            foreach ($order->payments as $payment) {
                $historico .= " ".$payment['method'];
                $contaAReceberAmount += $payment['amount'];
            }

            /* if ($contaAReceberAmount < 79.00) {
                $contaAReceberAmount = $this->sumDeliveryTax($order->fees, $contaAReceberAmount);
            } */

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
                $response = $this->blingContaAReceber($xml);
                // Dar baixar contas a receber
                if ($response) {
                    $contaAReceberID = $response['retorno']['contasreceber'][0]['contaReceber']['id'];

                    Payment::where('order_id', $order['id'])->update(['bling_id' => $contaAReceberID]);

                   /*  $dataLiquidacao = new DateTime('NOW');

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

                    $this->blingBaixaContaAReceber($contaAReceberID, $xmlBaixa); */

                }
            } else {
                Log::info("[SEND_TO_BLING][OFF] - [CONTA A RECEBER] - " . $contaAReceberAmount);
            }

            // Change bling_send_flag
            if (env('SEND_TO_BLING')) {
                Order::where('id', $order['id'])
                    ->update(['bling_send_flag' => true]);
            }
        }
    }

    public function getOrdersFromLast30Days()
    {
        $dateLast30Days = date('Y-m-d h:m:s', strtotime("-30 days", null));
        $orders = Order::where('created_in', '>=', $dateLast30Days)->get();

        return $orders;
    }

    public function getOrders()
    {
        GetNewOrdersJob::dispatch();
    }
}
