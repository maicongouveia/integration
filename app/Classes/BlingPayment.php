<?php

namespace App\Classes;

use App\Http\Controllers\MercadoLivre;

class BlingPayment extends Payment{

    private $payer;

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
                "valor"              => "", //obrigatorio
                "histórico"          => "",
                "categoria"          => "",
                "portador"           => "",
                "idFormaPagamento"   => "",
                "ocorrencia"         => array(//obrigatorio
                                                "ocorrenciaTipo"      => "", //obrigatorio
                                                "diaVencimento"       => "",
                                                "nroParcelas"         => $this->installments,
                                                "diaSemanaVencimento" => "",
                ),
                "fornecedor"         => array(//obrigatorio
                                                "nome"        => "",//obrigatorio
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
    }

    public function createRevenue(){
        $this->payer = $this->getPayer();
        $revenue = array(
            "contareceber" => array(
                "dataEmissao" => "",
                "vencimentoOriginal" => "",
                "competencia" => "",
                "nroDocumento" => "",
                "valor" => "", //obrigatorio
                "historico" => "",
                "categoria" => "",
                "idFormaPagamento" => "",
                "portador" => "",
                "vendedor" => "",
                "ocorrencia" => array(//obrigatorio
                    "ocorrenciaTipo" => "",//obrigatorio
                    "diaVencimento" => "",
                    "nroParcelas" => $this->payment->installments,
                ),
                "cliente" => array(//obrigatorio
                    "nome" => $this->payer->nome,//obrigatorio
                    "cpf_cnpj" => $this->payer['identification']['number'],
                    "email" => $this->payer['email'],
                ),
            ),
        );
    }
}