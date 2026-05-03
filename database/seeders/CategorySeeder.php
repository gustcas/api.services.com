<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'Capacitación',
            'Certificados Medicos',
            'Plan de Saneamiento',
            'Fumigacion',
            'Asesoria'
        ];

        foreach ($categories as $category) {

            Category::create([
                'name' => $category,
                'slug' => Str::slug($category),
                'description' => $category,
                'is_active' => true
            ]);

        }
    }
}
