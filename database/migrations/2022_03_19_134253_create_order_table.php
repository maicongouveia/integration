<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'order',
            function (Blueprint $table) {
                $table->id();
                $table->string('order_id');
                $table->unsignedBigInteger('bling_id');
                $table->string('invoice')->nullable();
                $table->date('payment_date');
                $table->boolean('need_update_flag');
                $table->boolean('bling_send_flag');
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order');
    }
}
