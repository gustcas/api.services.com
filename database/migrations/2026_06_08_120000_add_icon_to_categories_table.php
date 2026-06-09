<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIconToCategoriesTable extends Migration
{
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'icon_key')) {
                $table->string('icon_key')->nullable()->after('description');
            }
        });
    }
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon_key');
        });
    }
}
