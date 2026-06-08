<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAsecalidadAccountToCategoriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('categories', 'asecalidad_account_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unsignedBigInteger('asecalidad_account_id')->nullable()->after('is_active');
                $table->foreign('asecalidad_account_id')->references('id')->on('payout_accounts')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['asecalidad_account_id']);
            $table->dropColumn('asecalidad_account_id');
        });
    }
}
