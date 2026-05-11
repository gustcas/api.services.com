<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRatingsTable extends Migration
{
    public function up()
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('rater_id');   // quien califica
            $table->unsignedBigInteger('ratee_id');   // quien es calificado
            $table->tinyInteger('score');             // 1-5
            $table->text('comment')->nullable();
            $table->enum('type', ['client_to_professional', 'professional_to_client']);
            $table->timestamps();

            $table->foreign('service_request_id')->references('id')->on('service_requests')->onDelete('cascade');
            $table->foreign('rater_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ratee_id')->references('id')->on('users')->onDelete('cascade');

            // Un calificador solo puede calificar una vez por solicitud y tipo
            $table->unique(['service_request_id', 'rater_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ratings');
    }
}
