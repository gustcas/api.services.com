<?php

use Illuminate\Database\Migrations\Migration;

class MakeDescriptionNullableInServiceRequests extends Migration
{
    public function up()
    {
        \DB::statement('ALTER TABLE service_requests MODIFY description TEXT NULL');
    }

    public function down()
    {
        \DB::statement('ALTER TABLE service_requests MODIFY description TEXT NOT NULL');
    }
}
