<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutAccountsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('payout_accounts')) {
            Schema::create('payout_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('entity_name');
                $table->string('entity_type');
                $table->string('bank_name')->nullable();
                $table->string('bank_code')->nullable();
                $table->string('account_type')->nullable();
                $table->string('account_number');
                $table->string('account_holder');
                $table->string('document_number')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('category_payout_accounts')) {
            Schema::create('category_payout_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained()->onDelete('cascade');
                $table->foreignId('payout_account_id')->constrained()->onDelete('cascade');
                $table->string('entity_type');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('category_payout_accounts');
        Schema::dropIfExists('payout_accounts');
    }
}
