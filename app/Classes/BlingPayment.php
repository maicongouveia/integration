<?php

namespace App\Classes;

use App\Classes\MercadoLivre;

class BlingPayment extends Payment{

    private $payer;

    private $bills;

    public function getPayer(){
        $mercadoPago = new MercadoLivre();
        return $mercadoPago->getPayer($this->payer['id']);

    }

    public function createBill(){
        $bill = array(
            "contapagar" => array(
                "dataEmissão"        => "",
                "vencimentoOriginal" => "",
                "competencia"        => "",
                "nroDocumento"       => "",
                "valor"              => "",
                "histórico"          => "",
                "categoria"          => "",
                "portador"           => "",
                "idFormaPagamento"   => "",
                "ocorrencia"         => array(
                                                "ocorrenciaTipo"      => "",
                                                "diaVencimento"       => "",
                                                "nroParcelas"         => $this->installments,
                                                "diaSemanaVencimento" => "",
                ),
                "fornecedor"         => array(
                                                "nome"        => "",
                                                "id"          => "",
                                                "cpf_cnpj"    => "",
                                                "tipoPessoa"  => "",
                                                "ie_rg"       => "",
                                                "endereco"    => "",
                                                "numero"      => "",
                                                "complemento" => "",
                                                "cidade"      => "",
                                                "bairro"      => "",
                                                "cep"         => "",
                                                "uf"          => "",
                                                "email"       => "",
                                                "fone"        => "",
                                                "celular"     => "",
                ),

                

            )
        );

        return $bill;
    }

    public function createRevenue(){
        $this->payer = $this->getPayer();
        $revenue = array(
            "contareceber" => array(
                "dataEmissao" => "",
                "vencimentoOriginal" => "",
                "competencia" => "",
                "nroDocumento" => "",
                "valor" => "",
                "historico" => "",
                "categoria" => "",
                "idFormaPagamento" => "",
                "portador" => "",
                "vendedor" => "",
                "ocorrencia" => array(
                    "ocorrenciaTipo" => "",
                    "diaVencimento" => "",
                    "nroParcelas" => $this->payment->installments,
                ),
                "cliente" => array(
                    "nome" => $this->payer->nome,
                    "cpf_cnpj" => $this->payer['identification']['number'],
                    "email" => $this->payer['email'],
                ),
            ),
        );
        return $revenue;
    }

    public function getBills(){
        $this->bills = array(
            "bills" => array(),
            "reveneu" => array(),
        );
        if ($this->payment->shipment_cost){
            $this->bills['bills'][] = $this->createBill();
        }

        if ($this->payment->fee_cost){
            $this->bills['bills'][] = $this->createBill();
        }

        if ($this->payment->fee_cost){
            $this->bills['reveneu'][] = $this->createRevenue();
        }

        return $this->bills;
    }
}