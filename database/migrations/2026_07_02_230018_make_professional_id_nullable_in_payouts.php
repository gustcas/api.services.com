<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeProfessionalIdNullableInPayouts extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE payouts MODIFY COLUMN professional_id BIGINT UNSIGNED NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE payouts MODIFY COLUMN professional_id BIGINT UNSIGNED NOT NULL');
    }
}
