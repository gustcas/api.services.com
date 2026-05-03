<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProfessionalsAddServiceId extends Migration
{
    public function up()
    {
        Schema::table('professionals', function (Blueprint $table) {

            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');

            $table->foreignId('service_id')
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();

        });
    }

    public function down()
    {
        Schema::table('professionals', function (Blueprint $table) {

            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');

            $table->foreignId('category_id')
                ->constrained();

        });
    }
}