<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\Category;

class ServiceSeeder extends Seeder
{
    public function run()
    {

        $capacitacion = Category::where('slug','capacitacion')->first();
        $certificados = Category::where('slug','certificados-medicos')->first();
        $saneamiento = Category::where('slug','plan-de-saneamiento')->first();
        $fumigacion = Category::where('slug','fumigacion')->first();
        $asesoria = Category::where('slug','asesoria')->first();

        $services = [

            // CAPACITACION
            [
                'category_id' => $capacitacion->id,
                'name' => 'Virtual',
                'price' => 20000,
                'allies_percentage' => 25,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 42,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $capacitacion->id,
                'name' => 'Presencial',
                'price' => 25000,
                'allies_percentage' => 50,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 17,
                'maintenance_percentage' => 5,
            ],

            // CERTIFICADOS MEDICOS
            [
                'category_id' => $certificados->id,
                'name' => 'Con Resultados',
                'price' => 40000,
                'allies_percentage' => 50,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 17,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $certificados->id,
                'name' => 'Sin Resultados',
                'price' => 23000,
                'allies_percentage' => 50,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 17,
                'maintenance_percentage' => 5,
            ],

            // PLAN DE SANEAMIENTO
            [
                'category_id' => $saneamiento->id,
                'name' => 'Restaurante',
                'price' => 200000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Carniceria',
                'price' => 230000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Supermercado',
                'price' => 190000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Fruver',
                'price' => 190000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Panaderia',
                'price' => 200000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Bar',
                'price' => 140000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Cigarreria',
                'price' => 140000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $saneamiento->id,
                'name' => 'Complementarios',
                'price' => 180000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],

            // FUMIGACION
            [
                'category_id' => $fumigacion->id,
                'name' => 'Servicio',
                'price' => 65000,
                'allies_percentage' => 25,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 42,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $fumigacion->id,
                'name' => 'Visita de Diagnostico',
                'price' => 150000,
                'allies_percentage' => 56,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 11,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $fumigacion->id,
                'name' => 'Plan de Choque',
                'price' => 420000,
                'allies_percentage' => 53,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 14,
                'maintenance_percentage' => 5,
            ],

            // ASESORIA
            [
                'category_id' => $asesoria->id,
                'name' => 'Asesoria',
                'price' => 200000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],
            [
                'category_id' => $asesoria->id,
                'name' => 'Asesoria General',
                'price' => 100000,
                'allies_percentage' => 0,
                'payment_gateway_commission' => 8,
                'imavicx_commission' => 20,
                'asecalidad_commission' => 67,
                'maintenance_percentage' => 5,
            ],

        ];

        foreach ($services as $service) {

            Service::create([
                'category_id' => $service['category_id'],
                'name' => $service['name'],
                'price' => $service['price'],
                'allies_percentage' => $service['allies_percentage'],
                'payment_gateway_commission' => $service['payment_gateway_commission'],
                'imavicx_commission' => $service['imavicx_commission'],
                'asecalidad_commission' => $service['asecalidad_commission'],
                'maintenance_percentage' => $service['maintenance_percentage'],
                'is_active' => true
            ]);

        }

    }
}
