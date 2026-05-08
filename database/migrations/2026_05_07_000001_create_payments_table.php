<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();

            // Referencia única enviada a Wompi
            $table->string('reference')->unique();

            // Datos del monto
            $table->unsignedBigInteger('amount_in_cents');
            $table->string('currency', 3)->default('COP');

            // Respuesta de Wompi
            $table->string('wompi_transaction_id')->nullable();
            $table->string('wompi_status')->nullable(); // PENDING, APPROVED, DECLINED, ERROR

            // Estado interno
            // pending | approved | failed
            $table->string('status')->default('pending');

            // Datos extra de la transacción (JSON)
            $table->json('wompi_data')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index('status');
        });

        // Agregar columna payment_status a service_requests
        Schema::table('service_requests', function (Blueprint $table) {
            // pending_payment | paid | payment_failed
            $table->string('payment_status')->default('pending_payment')->after('status');
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
        Schema::dropIfExists('payments');
    }
}
