<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminLogsTable extends Migration
{
    public function up()
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('admin_name');
            $table->string('action');        // create | update | delete | verify | toggle
            $table->string('entity');        // user | category | service | sub-admin
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('description');
            $table->json('meta')->nullable(); // datos extra (old/new values, etc.)
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['admin_id', 'created_at']);
            $table->index(['entity', 'entity_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_logs');
    }
}
