<?php

namespace Database\Seeders;

use App\Models\TipoPregunta;
use Illuminate\Database\Seeder;

class TipoPreguntaSeeder extends Seeder
{
    /**
     * Inserta los tipos de pregunta para cuestionarios: Párrafo y Varias opciones.
     * Ejecutar: php artisan db:seed --class=TipoPreguntaSeeder
     */
    public function run(): void
    {
        $tipos = ['Párrafo', 'Varias opciones'];
        foreach ($tipos as $nombre) {
            TipoPregunta::firstOrCreate(
                ['tipoPregunta' => $nombre],
                ['tipoPregunta' => $nombre]
            );
        }
    }
}
