<?php

namespace App\Console\Commands;

use App\Http\Controllers\Integration;
use App\Http\Controllers\Mercadolivre;
use Carbon\Carbon;
use DateTime;
use DateTimeImmutable;
use Illuminate\Console\Command;

class getOrdersByDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:getOrdersByDate {from} {to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pegar pedidos por periodo';

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
        $from = $this->argument('from');
        $to = $this->argument('to');

        $from = DateTimeImmutable::createFromFormat('d/m/Y h:i', $from."00:00");
        $from = $from->format(Datetime::ATOM);

        $to = DateTimeImmutable::createFromFormat('d/m/Y h:i', $to."00:00");
        $to = $to->format(Datetime::ATOM);

        //$this->info("From: $from - To: $to");

        $date = [
            "from" => $from,
            "to" => $to,
        ];

        $mercadoLivre = new Mercadolivre();
        $results = $mercadoLivre->getOrdersByDate($date);
        $orders =  $mercadoLivre->responseOrdersHandler($results);
        $integration = new Integration();
        $integration->registerOrdersLocal($orders);
    }
}
