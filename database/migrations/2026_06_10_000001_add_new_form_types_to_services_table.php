<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFormTypesToServicesTable extends Migration
{
    public function up()
    {
        \DB::statement("ALTER TABLE services MODIFY COLUMN form_type ENUM(
            'presencial',
            'virtual',
            'certificacion_virtual',
            'certificacion_presencial',
            'presencial_participantes',
            'virtual_participantes'
        ) NOT NULL DEFAULT 'presencial'");
    }

    public function down()
    {
        \DB::statement("ALTER TABLE services MODIFY COLUMN form_type ENUM(
            'presencial',
            'virtual',
            'certificacion_virtual',
            'certificacion_presencial'
        ) NOT NULL DEFAULT 'presencial'");
    }
}
