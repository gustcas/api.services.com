<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEntityTypeToPayoutsTable extends Migration
{
    public function up()
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('entity_type')->default('professional')->after('triggered_by');
        });
    }

    public function down()
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('entity_type');
        });
    }
}
