<?php

namespace App\Console\Commands;

use App\Http\Controllers\Integration;
use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a test with one Order';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $integration = new Integration();
        $integration->getOrders();
        $integration->enrichOrders(1);
        $integration->registerOrdersBling(1);

        $orders = $integration->getOrdersToUpdateStatus(5);
        $integration->updateOrdersStatus($orders);

        $orders = $integration->getPaidOrdersToSend(5);
        foreach ($orders as $order){$integration->sendBaixaToBling($order);}
    }
}
