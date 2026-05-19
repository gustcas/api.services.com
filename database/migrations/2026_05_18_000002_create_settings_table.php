<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Valores por defecto
        $defaults = [
            'platform_name'          => 'e-Service',
            'contact_email'          => 'soporte@e-service.co',
            'support_phone'          => '',
            'commission_rate'        => '10',
            'min_withdrawal'         => '50000',
            'wompi_public_key'       => '',
            'alerts_email'           => '',
            'maintenance_mode'       => '0',
            'allow_registration'     => '1',
            'allow_pro_registration' => '1',
            'email_on_pro_approved'  => '1',
            'email_on_completed'     => '1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
