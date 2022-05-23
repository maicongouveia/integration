<?php

namespace App\Jobs;

use App\Http\Controllers\Integration;
use App\Http\Controllers\Mercadolivre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetNewOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mercadoLivre;
    public $integration;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->mercadoLivre = new Mercadolivre();
        $this->integration  = new Integration();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $responseOrders = $this->mercadoLivre->getOrders();

        if($responseOrders) {
            $orders = $this->mercadoLivre->responseOrdersHandler($responseOrders);
            $this->integration->registerOrdersLocal($orders);
        }
        else {
            Log::info("No orders to register locally");
        }
    }
}
