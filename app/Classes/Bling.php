<?php

namespace App\Classes;

use Illuminate\Support\Facades\Http;

class Bling
{
    private $url;

    private $body;

    public function __construct() {
        $this->url = 'https://bling.com.br/b/Api/v2/';
        $this->body = ['apikey' => env('BLING_API_KEY', "MERCADO_LIVRE_ACCESS_TOKEN")];
    }

    public function registerBills($bills){
        $url = $this->url;
        if (count($bills['revenue']) > 0){
            foreach($bills['revenue'] as $revenue) {
                $response = Http::post($url);
            }
        }

        $response = true;
        return $response;
    }
}