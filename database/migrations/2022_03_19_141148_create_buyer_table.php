<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'buyer', 
            function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id'); 
                $table->foreign('order_id')->references('id')->on('order');
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('identificationType')->nullable();
                $table->string('identificationNumber')->nullable();
                $table->string('phone')->nullable();
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
        Schema::dropIfExists('buyer');
    }
}
