<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdminConfirmOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
             $table->enum('admin_confirm', ['0', '1'])
                    ->nullable()
                    ->default('0')
                    ->comment('0 = Not Confirmed, 1 = Confirmed')
                    ->after('order_status');
            $table->enum('redeem_point_status', ['0', '1'])
                    ->nullable()
                    ->default('0')
                    ->comment('0 = Not redeem, 1 = Redeem')
                    ->after('admin_confirm');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
}
