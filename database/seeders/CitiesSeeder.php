<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;

class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            // Amazonas
            ['name' => 'Leticia',           'department' => 'Amazonas'],
            ['name' => 'Puerto Nariño',      'department' => 'Amazonas'],

            // Antioquia
            ['name' => 'Medellín',           'department' => 'Antioquia'],
            ['name' => 'Bello',              'department' => 'Antioquia'],
            ['name' => 'Itagüí',             'department' => 'Antioquia'],
            ['name' => 'Envigado',           'department' => 'Antioquia'],
            ['name' => 'Apartadó',           'department' => 'Antioquia'],
            ['name' => 'Turbo',              'department' => 'Antioquia'],
            ['name' => 'Rionegro',           'department' => 'Antioquia'],
            ['name' => 'Caucasia',           'department' => 'Antioquia'],
            ['name' => 'Copacabana',         'department' => 'Antioquia'],
            ['name' => 'La Estrella',        'department' => 'Antioquia'],
            ['name' => 'Sabaneta',           'department' => 'Antioquia'],
            ['name' => 'Caldas',             'department' => 'Antioquia'],
            ['name' => 'Girardota',          'department' => 'Antioquia'],
            ['name' => 'Barbosa',            'department' => 'Antioquia'],
            ['name' => 'Chigorodó',          'department' => 'Antioquia'],
            ['name' => 'Yarumal',            'department' => 'Antioquia'],
            ['name' => 'Puerto Berrío',      'department' => 'Antioquia'],
            ['name' => 'Andes',              'department' => 'Antioquia'],
            ['name' => 'Santa Fe de Antioquia', 'department' => 'Antioquia'],
            ['name' => 'Marinilla',          'department' => 'Antioquia'],
            ['name' => 'El Carmen de Viboral', 'department' => 'Antioquia'],
            ['name' => 'Segovia',            'department' => 'Antioquia'],
            ['name' => 'Amalfi',             'department' => 'Antioquia'],

            // Arauca
            ['name' => 'Arauca',             'department' => 'Arauca'],
            ['name' => 'Saravena',           'department' => 'Arauca'],
            ['name' => 'Tame',               'department' => 'Arauca'],
            ['name' => 'Fortul',             'department' => 'Arauca'],
            ['name' => 'Arauquita',          'department' => 'Arauca'],

            // Atlántico
            ['name' => 'Barranquilla',       'department' => 'Atlántico'],
            ['name' => 'Soledad',            'department' => 'Atlántico'],
            ['name' => 'Malambo',            'department' => 'Atlántico'],
            ['name' => 'Sabanalarga',        'department' => 'Atlántico'],
            ['name' => 'Baranoa',            'department' => 'Atlántico'],
            ['name' => 'Puerto Colombia',    'department' => 'Atlántico'],
            ['name' => 'Galapa',             'department' => 'Atlántico'],
            ['name' => 'Palmar de Varela',   'department' => 'Atlántico'],

            // Bolívar
            ['name' => 'Cartagena',          'department' => 'Bolívar'],
            ['name' => 'Magangué',           'department' => 'Bolívar'],
            ['name' => 'Turbaco',            'department' => 'Bolívar'],
            ['name' => 'El Carmen de Bolívar', 'department' => 'Bolívar'],
            ['name' => 'Mompox',             'department' => 'Bolívar'],
            ['name' => 'Arjona',             'department' => 'Bolívar'],
            ['name' => 'Villanueva',         'department' => 'Bolívar'],

            // Boyacá
            ['name' => 'Tunja',              'department' => 'Boyacá'],
            ['name' => 'Duitama',            'department' => 'Boyacá'],
            ['name' => 'Sogamoso',           'department' => 'Boyacá'],
            ['name' => 'Chiquinquirá',       'department' => 'Boyacá'],
            ['name' => 'Puerto Boyacá',      'department' => 'Boyacá'],
            ['name' => 'Paipa',              'department' => 'Boyacá'],
            ['name' => 'Moniquirá',          'department' => 'Boyacá'],
            ['name' => 'Villa de Leyva',     'department' => 'Boyacá'],

            // Caldas
            ['name' => 'Manizales',          'department' => 'Caldas'],
            ['name' => 'La Dorada',          'department' => 'Caldas'],
            ['name' => 'Chinchiná',          'department' => 'Caldas'],
            ['name' => 'Riosucio',           'department' => 'Caldas'],
            ['name' => 'Villamaría',         'department' => 'Caldas'],
            ['name' => 'Anserma',            'department' => 'Caldas'],
            ['name' => 'Manzanares',         'department' => 'Caldas'],
            ['name' => 'Salamina',           'department' => 'Caldas'],

            // Caquetá
            ['name' => 'Florencia',          'department' => 'Caquetá'],
            ['name' => 'San Vicente del Caguán', 'department' => 'Caquetá'],
            ['name' => 'Puerto Rico',        'department' => 'Caquetá'],
            ['name' => 'Belén de los Andaquíes', 'department' => 'Caquetá'],

            // Casanare
            ['name' => 'Yopal',              'department' => 'Casanare'],
            ['name' => 'Aguazul',            'department' => 'Casanare'],
            ['name' => 'Villanueva',         'department' => 'Casanare'],
            ['name' => 'Tauramena',          'department' => 'Casanare'],
            ['name' => 'Paz de Ariporo',     'department' => 'Casanare'],

            // Cauca
            ['name' => 'Popayán',            'department' => 'Cauca'],
            ['name' => 'Santander de Quilichao', 'department' => 'Cauca'],
            ['name' => 'Puerto Tejada',      'department' => 'Cauca'],
            ['name' => 'Patía',              'department' => 'Cauca'],
            ['name' => 'Caloto',             'department' => 'Cauca'],
            ['name' => 'Corinto',            'department' => 'Cauca'],

            // Cesar
            ['name' => 'Valledupar',         'department' => 'Cesar'],
            ['name' => 'Aguachica',          'department' => 'Cesar'],
            ['name' => 'Codazzi',            'department' => 'Cesar'],
            ['name' => 'Bosconia',           'department' => 'Cesar'],
            ['name' => 'La Jagua de Ibirico', 'department' => 'Cesar'],
            ['name' => 'Chiriguaná',         'department' => 'Cesar'],

            // Chocó
            ['name' => 'Quibdó',             'department' => 'Chocó'],
            ['name' => 'Istmina',            'department' => 'Chocó'],
            ['name' => 'Tumaco',             'department' => 'Chocó'],
            ['name' => 'Condoto',            'department' => 'Chocó'],
            ['name' => 'Tadó',               'department' => 'Chocó'],

            // Córdoba
            ['name' => 'Montería',           'department' => 'Córdoba'],
            ['name' => 'Cereté',             'department' => 'Córdoba'],
            ['name' => 'Lorica',             'department' => 'Córdoba'],
            ['name' => 'Sahagún',            'department' => 'Córdoba'],
            ['name' => 'Montelíbano',        'department' => 'Córdoba'],
            ['name' => 'Tierralta',          'department' => 'Córdoba'],
            ['name' => 'Planeta Rica',       'department' => 'Córdoba'],

            // Cundinamarca
            ['name' => 'Bogotá D.C.',        'department' => 'Cundinamarca'],
            ['name' => 'Soacha',             'department' => 'Cundinamarca'],
            ['name' => 'Facatativá',         'department' => 'Cundinamarca'],
            ['name' => 'Zipaquirá',          'department' => 'Cundinamarca'],
            ['name' => 'Chía',               'department' => 'Cundinamarca'],
            ['name' => 'Fusagasugá',         'department' => 'Cundinamarca'],
            ['name' => 'Mosquera',           'department' => 'Cundinamarca'],
            ['name' => 'Madrid',             'department' => 'Cundinamarca'],
            ['name' => 'Funza',              'department' => 'Cundinamarca'],
            ['name' => 'Cajicá',             'department' => 'Cundinamarca'],
            ['name' => 'Tocancipá',          'department' => 'Cundinamarca'],
            ['name' => 'Sopó',               'department' => 'Cundinamarca'],
            ['name' => 'La Mesa',            'department' => 'Cundinamarca'],
            ['name' => 'Girardot',           'department' => 'Cundinamarca'],
            ['name' => 'Villeta',            'department' => 'Cundinamarca'],
            ['name' => 'Ubaté',              'department' => 'Cundinamarca'],

            // Guainía
            ['name' => 'Inírida',            'department' => 'Guainía'],

            // Guaviare
            ['name' => 'San José del Guaviare', 'department' => 'Guaviare'],
            ['name' => 'El Retorno',         'department' => 'Guaviare'],

            // Huila
            ['name' => 'Neiva',              'department' => 'Huila'],
            ['name' => 'Pitalito',           'department' => 'Huila'],
            ['name' => 'Garzón',             'department' => 'Huila'],
            ['name' => 'La Plata',           'department' => 'Huila'],
            ['name' => 'Campoalegre',        'department' => 'Huila'],
            ['name' => 'Rivera',             'department' => 'Huila'],

            // La Guajira
            ['name' => 'Riohacha',           'department' => 'La Guajira'],
            ['name' => 'Maicao',             'department' => 'La Guajira'],
            ['name' => 'Uribia',             'department' => 'La Guajira'],
            ['name' => 'Manaure',            'department' => 'La Guajira'],
            ['name' => 'San Juan del Cesar',  'department' => 'La Guajira'],

            // Magdalena
            ['name' => 'Santa Marta',        'department' => 'Magdalena'],
            ['name' => 'Ciénaga',            'department' => 'Magdalena'],
            ['name' => 'Fundación',          'department' => 'Magdalena'],
            ['name' => 'El Banco',           'department' => 'Magdalena'],
            ['name' => 'Plato',              'department' => 'Magdalena'],

            // Meta
            ['name' => 'Villavicencio',      'department' => 'Meta'],
            ['name' => 'Acacías',            'department' => 'Meta'],
            ['name' => 'Granada',            'department' => 'Meta'],
            ['name' => 'Puerto López',       'department' => 'Meta'],
            ['name' => 'San Martín',         'department' => 'Meta'],
            ['name' => 'Puerto Gaitán',      'department' => 'Meta'],

            // Nariño
            ['name' => 'Pasto',              'department' => 'Nariño'],
            ['name' => 'Tumaco',             'department' => 'Nariño'],
            ['name' => 'Ipiales',            'department' => 'Nariño'],
            ['name' => 'Túquerres',          'department' => 'Nariño'],
            ['name' => 'La Unión',           'department' => 'Nariño'],

            // Norte de Santander
            ['name' => 'Cúcuta',             'department' => 'Norte de Santander'],
            ['name' => 'Ocaña',              'department' => 'Norte de Santander'],
            ['name' => 'Pamplona',           'department' => 'Norte de Santander'],
            ['name' => 'Villa del Rosario',  'department' => 'Norte de Santander'],
            ['name' => 'Los Patios',         'department' => 'Norte de Santander'],
            ['name' => 'El Zulia',           'department' => 'Norte de Santander'],
            ['name' => 'Tibú',               'department' => 'Norte de Santander'],

            // Putumayo
            ['name' => 'Mocoa',              'department' => 'Putumayo'],
            ['name' => 'Puerto Asís',        'department' => 'Putumayo'],
            ['name' => 'Orito',              'department' => 'Putumayo'],
            ['name' => 'Valle del Guamuez',  'department' => 'Putumayo'],

            // Quindío
            ['name' => 'Armenia',            'department' => 'Quindío'],
            ['name' => 'Calarcá',            'department' => 'Quindío'],
            ['name' => 'Montenegro',         'department' => 'Quindío'],
            ['name' => 'Quimbaya',           'department' => 'Quindío'],
            ['name' => 'La Tebaida',         'department' => 'Quindío'],

            // Risaralda
            ['name' => 'Pereira',            'department' => 'Risaralda'],
            ['name' => 'Dosquebradas',       'department' => 'Risaralda'],
            ['name' => 'Santa Rosa de Cabal', 'department' => 'Risaralda'],
            ['name' => 'La Virginia',        'department' => 'Risaralda'],
            ['name' => 'Belén de Umbría',    'department' => 'Risaralda'],

            // San Andrés y Providencia
            ['name' => 'San Andrés',         'department' => 'San Andrés y Providencia'],
            ['name' => 'Providencia',        'department' => 'San Andrés y Providencia'],

            // Santander
            ['name' => 'Bucaramanga',        'department' => 'Santander'],
            ['name' => 'Floridablanca',      'department' => 'Santander'],
            ['name' => 'Girón',              'department' => 'Santander'],
            ['name' => 'Piedecuesta',        'department' => 'Santander'],
            ['name' => 'Barrancabermeja',    'department' => 'Santander'],
            ['name' => 'San Gil',            'department' => 'Santander'],
            ['name' => 'Socorro',            'department' => 'Santander'],
            ['name' => 'Vélez',              'department' => 'Santander'],
            ['name' => 'Málaga',             'department' => 'Santander'],

            // Sucre
            ['name' => 'Sincelejo',          'department' => 'Sucre'],
            ['name' => 'Corozal',            'department' => 'Sucre'],
            ['name' => 'Sampués',            'department' => 'Sucre'],
            ['name' => 'San Marcos',         'department' => 'Sucre'],
            ['name' => 'Tolú',               'department' => 'Sucre'],

            // Tolima
            ['name' => 'Ibagué',             'department' => 'Tolima'],
            ['name' => 'Espinal',            'department' => 'Tolima'],
            ['name' => 'Melgar',             'department' => 'Tolima'],
            ['name' => 'Honda',              'department' => 'Tolima'],
            ['name' => 'Líbano',             'department' => 'Tolima'],
            ['name' => 'Chaparral',          'department' => 'Tolima'],
            ['name' => 'Mariquita',          'department' => 'Tolima'],

            // Valle del Cauca
            ['name' => 'Cali',               'department' => 'Valle del Cauca'],
            ['name' => 'Buenaventura',       'department' => 'Valle del Cauca'],
            ['name' => 'Palmira',            'department' => 'Valle del Cauca'],
            ['name' => 'Tuluá',              'department' => 'Valle del Cauca'],
            ['name' => 'Buga',               'department' => 'Valle del Cauca'],
            ['name' => 'Cartago',            'department' => 'Valle del Cauca'],
            ['name' => 'Yumbo',              'department' => 'Valle del Cauca'],
            ['name' => 'Jamundí',            'department' => 'Valle del Cauca'],
            ['name' => 'Candelaria',         'department' => 'Valle del Cauca'],
            ['name' => 'Floridablanca',      'department' => 'Valle del Cauca'],
            ['name' => 'Roldanillo',         'department' => 'Valle del Cauca'],
            ['name' => 'Zarzal',             'department' => 'Valle del Cauca'],

            // Vaupés
            ['name' => 'Mitú',               'department' => 'Vaupés'],

            // Vichada
            ['name' => 'Puerto Carreño',     'department' => 'Vichada'],
            ['name' => 'La Primavera',       'department' => 'Vichada'],
        ];

        foreach ($cities as $city) {
            City::firstOrCreate(
                ['name' => $city['name'], 'department' => $city['department']],
                ['is_active' => true]
            );
        }
    }
}