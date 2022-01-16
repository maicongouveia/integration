<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivre extends Controller
{
    public function __construct()
    {
        $this->url = 'https://api.mercadolibre.com';
        $this->headers = ['Authorization' => env('MERCADO_LIVRE_ACCESS_TOKEN', "MERCADO_LIVRE_ACCESS_TOKEN")];
    }

    public function getPayment(String $colletion){
        try{
            $response = Http::withHeaders($this->headers)->get($this->url . $colletion, $this->headers);            
            if($response->status() != 200) {
                Log::warning("[getPayment]: Status: " . $response->status() . " - Body: " . $response->body());
                return null;
            }
            return $response->body();
        }catch(Exception $e){
            Log::error("[getPayment]: " . $e->getMessage());
            return null;
        }
    }
}
