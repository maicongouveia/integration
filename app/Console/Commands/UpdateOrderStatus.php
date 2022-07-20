<?php

namespace App\Console\Commands;

use App\Http\Controllers\Integration;
use Illuminate\Console\Command;

class UpdateOrderStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:updateOrdersStatus {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Orders status information';

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
        $quantity = $this->argument('quantity');
        $orders = $integration->getOrdersToUpdateStatus($quantity);
        $integration->updateOrdersStatus($orders);
    }
}
