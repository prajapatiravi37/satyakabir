<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ModifyOrderStatusEnumValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First, update any existing 'Completed' status to 'Delivered'
        DB::statement("UPDATE orders SET order_status = 'Delivered' WHERE order_status = 'Completed'");
        
        // Then modify the ENUM to include new values: Pending, Cancelled, Delivered, Confirm
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM('Pending', 'Cancelled', 'Delivered', 'Confirm') DEFAULT 'Pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert back to original ENUM values
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending'");
        
        // Update any 'Delivered' status back to 'Completed'
        DB::statement("UPDATE orders SET order_status = 'Completed' WHERE order_status = 'Delivered'");
        
        // Update any 'Confirm' status back to 'Pending'
        DB::statement("UPDATE orders SET order_status = 'Pending' WHERE order_status = 'Confirm'");
    }
}
