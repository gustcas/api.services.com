<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParticipantsToServiceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedInteger('people_count')->nullable()->after('service_time');
            $table->json('people_names')->nullable()->after('people_count');
            $table->json('people_identifications')->nullable()->after('people_names');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'people_count',
                'people_names',
                'people_identifications',
            ]);
        });
    }
}
