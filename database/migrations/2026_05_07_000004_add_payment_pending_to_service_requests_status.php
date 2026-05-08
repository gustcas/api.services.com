<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPaymentPendingToServiceRequestsStatus extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE service_requests MODIFY COLUMN status ENUM('payment_pending','pending','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE service_requests MODIFY COLUMN status ENUM('pending','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
}
