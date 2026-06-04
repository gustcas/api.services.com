<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompanyLocalityToServiceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('company_locality')->nullable()->after('company_phone');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('company_locality');
        });
    }
}
