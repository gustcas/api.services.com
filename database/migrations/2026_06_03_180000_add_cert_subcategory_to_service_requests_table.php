<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCertSubcategoryToServiceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('cert_subcategory')->nullable()->after('cycle');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('cert_subcategory');
        });
    }
}
