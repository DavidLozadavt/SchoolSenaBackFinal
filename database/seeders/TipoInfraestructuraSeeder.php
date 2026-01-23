<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TipoInfraestructuraSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Aula',
            'Aula especializada',
            'Salón',
            'Laboratorio',
            'Sala de informática',
            'Sala de idiomas',
            'Biblioteca',
            'Auditorio',
            'Oficina administrativa',
            'Sala de profesores',
            'Baños',
            'Cafetería',
            'Cancha deportiva',
            'Gimnasio',
            'Patio',
            'Bodega',
            'Parqueadero',
            'salon prescolar',
            'Otros',
        ];

        foreach ($tipos as $tipo) {
            DB::table('tiposinfraestructura')->insert([
                'nombre' => $tipo,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
