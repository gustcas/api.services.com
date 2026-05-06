<?php

use Illuminate\Database\Migrations\Migration;

class AddLatLngToServiceRequests extends Migration
{
    public function up()
    {
        \DB::statement('ALTER TABLE service_requests ADD COLUMN lat DECIMAL(10,7) NULL AFTER address');
        \DB::statement('ALTER TABLE service_requests ADD COLUMN lng DECIMAL(10,7) NULL AFTER lat');
    }

    public function down()
    {
        \DB::statement('ALTER TABLE service_requests DROP COLUMN lat');
        \DB::statement('ALTER TABLE service_requests DROP COLUMN lng');
    }
}
