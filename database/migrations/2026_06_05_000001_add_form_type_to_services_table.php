<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFormTypeToServicesTable extends Migration
{
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('form_type')->default('presencial')->after('name');
        });
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('form_type');
        });
    }
}
