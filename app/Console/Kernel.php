<?php

namespace App\Console;

use App\Http\Controllers\Integration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            $integration = new Integration();
            $integration->getOrders();
        })->everyTenMinutes();

        $schedule->call(function () {
            $integration = new Integration();
            $integration->enrichOrders(5);
        })->everyMinute();

        $schedule->call(function () {
            $integration = new Integration();
            $integration->registerOrdersBling(5);
        })->everyMinute();

        $schedule->call(function () {
            $integration = new Integration();
            $orders = $integration->getOrdersToUpdateStatus(5);
            $integration->updateOrdersStatus($orders);
        })->everyMinute();

        $schedule->call(function () {
            $integration = new Integration();
            $orders = $integration->getPaidOrdersToSend(5);
            foreach ($orders as $order){$integration->sendBaixaToBling($order);}
        })->everyMinute();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');

    }
}
