<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddSubAdminFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->json('permissions')->nullable()->after('is_super_admin');
        });

        // Todos los admins actuales se marcan como super admin
        DB::table('users')->where('role', 'admin')->update(['is_super_admin' => true]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'permissions']);
        });
    }
}
