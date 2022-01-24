<?php

namespace App\Classes;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivre
{
    public function __construct() {
        $this->url = 'https://api.mercadolibre.com';
        $this->headers = ['Authorization' => env('MERCADO_LIVRE_ACCESS_TOKEN', "MERCADO_LIVRE_ACCESS_TOKEN")];
    }

    public function getPayment(String $colletion){
        $this->fakeGetPaymentResponse();
        $url = $this->url . $colletion;
        try{

            Log::info("[getPayment] URL: $url - Header: $this->headers");
            $response = Http::withHeaders($this->headers)->get($url, $this->headers);            
            
            if($response->status() != 200) {
                Log::warning("[getPayment]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }

            Log::info("[getPayment] URL: $url - Response: " .$response->json());
            return $response->json();

        } catch (Exception $e) {
            Log::error("[getPayment]: " . $e->getMessage());
            return null;
        }
    }

    public function getPayer(Int $payer_id){
        $this->fakeGetPayerResponse();
        $url = 'https://api.mercadopago.com/v1/customers/' . $payer_id;
        try{
            Log::info("[getPayment] URL: $url - Header: $this->headers");
            $response = Http::withHeaders($this->headers)->get($url, $this->headers);

            if($response->status() != 200) {
                Log::warning("[getPayer]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }

            Log::info("[getPayment] URL: $url - Response: " .$response->json());
            return $response->json();
        } catch (Exception $e) {
            Log::error("[getPayer]: " . $e->getMessage());
            return null;
        }
    }

    public function fakeGetPaymentResponse(){
        return Http::fake(function ($request) {
            return Http::response('{
                "id": 1,
                "date_created": "2017-08-31T11:26:38.000Z",
                "date_approved": "2017-08-31T11:26:38.000Z",
                "date_last_updated": "2017-08-31T11:26:38.000Z",
                "money_release_date": "2017-09-14T11:26:38.000Z",
                "payment_method_id": "account_money",
                "payment_type_id": "credit_card",
                "status": "approved",
                "status_detail": "accredited",
                "currency_id": "BRL",
                "description": "Pago Pizza",
                "collector_id": 2,
                "payer": {
                  "id": 123,
                  "email": "afriend@gmail.com",
                  "identification": {
                    "type": "DNI",
                    "number": 12345678
                  },
                  "type": "customer"
                },
                "metadata": {},
                "additional_info": {},
                "order": {},
                "transaction_amount": 250,
                "transaction_amount_refunded": 0,
                "coupon_amount": 0,
                "transaction_details": {
                  "net_received_amount": 250,
                  "total_paid_amount": 250,
                  "overpaid_amount": 0,
                  "installment_amount": 250
                },
                "installments": 1,
                "card": {}
              }', 200);
        });
    }

    public function fakeGetPayerResponse(){
        return Http::fake(function ($request) {
            return Http::response('{
                "id": "470183340-cpunOI7UsIHlHr",
                "email": "comprador_mlb01+470183340@asdf12.com.br",
                "first_name": "Customer",
                "last_name": "Tester",
                "phone": {
                  "area_code": "11",
                  "number": "97654321"
                },
                "identification": {
                  "type": "CPF",
                  "number": "19119119100"
                },
                "address": {
                  "id": "1162600213",
                  "zip_code": "05187010",
                  "street_name": "Caetano Poli, 12"
                },
                "description": "Customer Test",
                "date_created": "2021-03-16T15:45:17.000-04:00",
                "metadata": {
                  "source_sync": "source_ws"
                },
                "default_address": "1162600213",
                "cards": {
                  "payment_method": {},
                  "security_code": {},
                  "issuer": {},
                  "cardholder": {}
                },
                "addresses": {
                  "id": "1162600213",
                  "street_name": "Caetano Poli, 12",
                  "zip_code": "05187010",
                  "city": {
                    "id": "BR-SP-44",
                    "name": "São Paulo"
                  },
                  "state": {
                    "id": "BR-SP",
                    "name": "São Paulo"
                  },
                  "country": {
                    "id": "BR",
                    "name": "Brasil"
                  },
                  "neighborhood": {
                    "name": "Jardim Ipanema (Zona Oeste)"
                  },
                  "municipality": {},
                  "date_created": "2021-03-16T15:45:17.000-04:00"
                },
                "live_mode": true
              }', 200);
        });
    }
}
