<?php

namespace App\Console\Commands;

use App\Http\Controllers\Integration;
use Illuminate\Console\Command;

class EnrichOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:EnrichOrders {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich Orders information and payment data';

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
        $integration->enrichOrders($this->argument('quantity'));
    }
}
