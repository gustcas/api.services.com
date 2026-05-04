<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeServiceIdNullableInProfessionals extends Migration
{
    public function up()
    {
        \DB::statement('ALTER TABLE professionals MODIFY service_id BIGINT UNSIGNED NULL');
    }

    public function down()
    {
        \DB::statement('ALTER TABLE professionals MODIFY service_id BIGINT UNSIGNED NOT NULL');
    }
}
