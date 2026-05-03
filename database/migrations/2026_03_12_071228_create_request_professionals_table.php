<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestProfessionalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_professionals', function (Blueprint $table) {

            $table->id();

            $table->foreignId('request_id')
            ->constrained('service_requests')
            ->cascadeOnDelete();

            $table->foreignId('professional_id')
            ->constrained()
            ->cascadeOnDelete();

            $table->enum('status',[
                'pending',
                'accepted',
                'rejected'
            ])->default('pending');

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
        Schema::dropIfExists('request_professionals');
    }
}
