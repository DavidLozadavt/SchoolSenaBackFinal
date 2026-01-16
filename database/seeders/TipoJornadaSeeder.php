<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TipoJornadaSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2025-01-14 06:33:47');

        DB::table('tipojornada')->insert([
            [
                'id' => 1,
                'nombreTipoJornada' => 'PROGRAMACION CHAT',
                'descripcion' => 'TIPO DE JORNADA ORIENTADO A ESTADOS DEL CHAT PERSONAL',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'nombreTipoJornada' => 'PROGRAMAS DE FORMACION',
                'descripcion' => 'TIPO DE JORNADA ORIENTADO A PROGRAMAS',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'nombreTipoJornada' => 'EVENTOS',
                'descripcion' => 'TIPO DE JORNADA ORIENTADO A EVENTOS',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'nombreTipoJornada' => 'MATERIAS',
                'descripcion' => 'TIPO DE JORNADA ORIENTADO A MATERIAS',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
