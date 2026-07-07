<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNetAmountToPayoutsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE payouts ADD COLUMN gross_amount DECIMAL(12,2) NULL AFTER amount');
        DB::statement('ALTER TABLE payouts ADD COLUMN discount_amount DECIMAL(12,2) NULL AFTER gross_amount');
        DB::statement('ALTER TABLE payouts ADD COLUMN net_amount DECIMAL(12,2) NULL AFTER discount_amount');
    }

    public function down()
    {
        DB::statement('ALTER TABLE payouts DROP COLUMN gross_amount');
        DB::statement('ALTER TABLE payouts DROP COLUMN discount_amount');
        DB::statement('ALTER TABLE payouts DROP COLUMN net_amount');
    }
}
