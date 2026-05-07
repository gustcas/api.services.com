<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('professional_categories', function (Blueprint $table) {
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['professional_id', 'category_id']);
        });

        // Migrar datos existentes: copiar category_id actual a la nueva tabla pivot
        \DB::statement('
            INSERT IGNORE INTO professional_categories (professional_id, category_id)
            SELECT id, category_id FROM professionals WHERE category_id IS NOT NULL
        ');

        // Hacer category_id nullable (ya no es la fuente de verdad, es solo cache)
        \DB::statement('ALTER TABLE professionals MODIFY category_id BIGINT UNSIGNED NULL');
    }

    public function down()
    {
        \DB::statement('ALTER TABLE professionals MODIFY category_id BIGINT UNSIGNED NOT NULL');
        Schema::dropIfExists('professional_categories');
    }
}
