<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColunmSendBaixaToBlingFlagToFeeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fee', function (Blueprint $table) {
            $table->boolean('send_baixa_to_bling');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fee', function (Blueprint $table) {
            $table->dropColumn('send_baixa_to_bling');
        });
    }
}
