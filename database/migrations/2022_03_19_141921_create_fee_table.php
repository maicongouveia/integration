<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'fee', 
            function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id'); 
                $table->foreign('order_id')->references('id')->on('order');
                $table->string('description');
                $table->string('amount');
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
        Schema::dropIfExists('fee');
    }
}
