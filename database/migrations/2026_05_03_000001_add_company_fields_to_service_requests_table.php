<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompanyFieldsToServiceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('people_identifications');
            $table->string('company_address')->nullable()->after('company_name');
            $table->string('company_owners')->nullable()->after('company_address');
            $table->string('company_nit')->nullable()->after('company_owners');
            $table->string('company_phone')->nullable()->after('company_nit');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_address',
                'company_owners',
                'company_nit',
                'company_phone',
            ]);
        });
    }
}
