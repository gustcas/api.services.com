<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisbursementStatusToServiceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('service_requests', 'disbursement_status')) {
                $table->string('disbursement_status')->default('pending')->after('payout_status');
            }
            if (!Schema::hasColumn('service_requests', 'disbursement_scheduled_at')) {
                $table->timestamp('disbursement_scheduled_at')->nullable();
            }
            if (!Schema::hasColumn('service_requests', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable();
            }
            if (!Schema::hasColumn('service_requests', 'disbursement_error')) {
                $table->text('disbursement_error')->nullable();
            }
            if (!Schema::hasColumn('service_requests', 'disbursement_attempts')) {
                $table->tinyInteger('disbursement_attempts')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'disbursement_status',
                'disbursement_scheduled_at',
                'disbursed_at',
                'disbursement_error',
                'disbursement_attempts',
            ]);
        });
    }
}
