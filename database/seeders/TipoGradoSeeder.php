<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TipoGradoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datos = [
            ['nombreTipoGrado' => 'BIMEMESTRE'],
            ['nombreTipoGrado' => 'TRIMEMESTRE'],
            ['nombreTipoGrado' => 'CUATRIMESTRE'],
            ['nombreTipoGrado' => 'SEMESTRE'],
            ['nombreTipoGrado' => 'AÑO'],
        ];

        $now = Carbon::now();

        foreach ($datos as $dato) {
            DB::table('tipoGrado')->insert([
                'nombreTipoGrado' => $dato['nombreTipoGrado'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}