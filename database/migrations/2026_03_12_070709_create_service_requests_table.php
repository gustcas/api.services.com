<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_requests', function (Blueprint $table) {

            $table->id();

            $table->foreignId('client_id')->constrained('users');

            $table->foreignId('category_id')->constrained();

            $table->foreignId('service_id')->constrained();

            $table->text('description');

            $table->string('address');

            $table->date('service_date');

            $table->time('service_time');

            $table->decimal('budget',10,2)->nullable();

            $table->enum('status',[
                'pending',
                'assigned',
                'completed',
                'cancelled'
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
        Schema::dropIfExists('service_requests');
    }
}
