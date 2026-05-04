<?php

use Illuminate\Database\Migrations\Migration;

class MakeAddressNullableInServiceRequests extends Migration
{
    public function up()
    {
        \DB::statement('ALTER TABLE service_requests MODIFY address VARCHAR(255) NULL');
    }

    public function down()
    {
        \DB::statement('ALTER TABLE service_requests MODIFY address VARCHAR(255) NOT NULL');
    }
}
