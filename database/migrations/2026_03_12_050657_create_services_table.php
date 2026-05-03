<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {

            $table->id();

            $table->foreignId('category_id')
            ->constrained()
            ->cascadeOnDelete();

            $table->string('name');

            $table->decimal('price',10,2)->default(0);

            $table->decimal('allies_percentage',5,2)->default(0);

            $table->decimal('payment_gateway_commission',5,2)->default(0);

            $table->decimal('imavicx_commission',5,2)->default(0);

            $table->decimal('asecalidad_commission',5,2)->default(0);

            $table->decimal('maintenance_percentage',5,2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('services');
    }
}
