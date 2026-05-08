<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutsTable extends Migration
{
    public function up()
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();

            // Referencia única enviada a Wompi Payouts
            $table->string('reference')->unique();

            // Monto neto que recibe el profesional (en pesos, no centavos)
            $table->decimal('amount', 12, 2);

            // Datos del destino (snapshot al momento del pago)
            $table->string('payment_method');            // bank_transfer | nequi | daviplata
            $table->string('bank_name')->nullable();
            $table->string('account_type')->nullable();
            $table->string('account_number');

            // Respuesta de Wompi Payouts
            $table->string('wompi_payout_id')->nullable();
            $table->string('wompi_status')->nullable();  // PENDING | APPROVED | DECLINED | ERROR

            // Estado interno
            // processing | approved | failed
            $table->string('status')->default('processing');

            // Tipo de trigger
            $table->string('triggered_by')->default('auto'); // auto | manual

            $table->json('wompi_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index('status');
        });

        // Columna de estado de dispersión en service_requests
        Schema::table('service_requests', function (Blueprint $table) {
            // pending_completion | pending_payout | payout_processing | payout_approved | payout_failed
            $table->string('payout_status')->default('pending_completion')->after('payment_status');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('payout_status');
        });
        Schema::dropIfExists('payouts');
    }
}
