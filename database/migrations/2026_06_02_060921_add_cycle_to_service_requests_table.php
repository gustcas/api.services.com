<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCycleToServiceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->tinyInteger('cycle')->nullable()->after('people_identifications');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('cycle');
        });
    }
}
