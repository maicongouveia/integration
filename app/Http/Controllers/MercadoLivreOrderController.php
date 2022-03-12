<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MercadoLivreOrderController extends Controller
{
    public function orders(Request $request) {
        $ml = new Mercadolivre();
        return view('welcome');
    }
}
