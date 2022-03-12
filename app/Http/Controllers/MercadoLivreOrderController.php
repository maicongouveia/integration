<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MercadoLivreOrderController extends Controller
{
    public function orders(Request $request) 
    {
        return view('welcome');
    }
}
