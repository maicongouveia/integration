<?php

namespace App\Classes;

class BlingPayment extends Payment{

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
    }

    public function createRevenue(){
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
                    "nroParcelas" => "",
                ),
                "cliente" => array(
                    "nome" => "",
                    "cpf_cnpj" => "",
                    "tipoPessoa" => "",
                    "ie_rg" => "",
                    "endereco" => "",
                    "numero" => "",
                    "complemento" => "",
                    "cidade" => "",
                    "bairro" => "",
                    "cep" => "",
                    "uf" => "",
                    "email" => "",
                    "fone" => "",
                    "celular" => "",
                ),
            ),
        );
    }
}