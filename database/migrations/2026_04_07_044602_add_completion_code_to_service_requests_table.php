<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('completion_code', 6)->nullable()->after('status');
            $table->timestamp('completion_code_expires_at')->nullable()->after('completion_code');
            $table->timestamp('completed_at')->nullable()->after('completion_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['completion_code', 'completion_code_expires_at', 'completed_at']);
        });
    }
};