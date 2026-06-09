<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIconToServicesTable extends Migration
{
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'icon_key')) {
                $table->string('icon_key')->nullable()->after('name');
            }
        });
    }
    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('icon_key');
        });
    }
}
