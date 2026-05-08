<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalPaymentInfosTable extends Migration
{
    public function up()
    {
        Schema::create('professional_payment_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->unique()->constrained()->cascadeOnDelete();

            // Método: bank_transfer | nequi | daviplata
            $table->string('payment_method')->default('bank_transfer');

            // Identificación del titular
            $table->string('id_type')->default('CC');   // CC, NIT, CE
            $table->string('id_number');
            $table->string('full_name');
            $table->string('email');

            // Datos bancarios (solo para bank_transfer)
            $table->string('bank_id')->nullable();      // UUID Wompi
            $table->string('bank_code')->nullable();    // ej: BANCOLOMBIA
            $table->string('bank_name')->nullable();    // ej: Bancolombia
            $table->string('account_type')->nullable(); // AHORROS | CORRIENTE

            // Número de cuenta (banco) o teléfono (Nequi/Daviplata)
            $table->string('account_number');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('professional_payment_infos');
    }
}
