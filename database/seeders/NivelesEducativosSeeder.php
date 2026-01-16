<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NivelesEducativosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
        ['nombreNivel' => 'PREESCOLAR'],
        ['nombreNivel' => 'PRIMARIA'],
        ['nombreNivel' => 'BACHILLER'],
        ['nombreNivel' => 'TECNICO'],
        ['nombreNivel' => 'TECNOLOGO'],
        ['nombreNivel' => 'PREGRADO'],
        ['nombreNivel' => 'POSTGRADO'],
    ];

    \DB::table('nivelEducativo')->insert($data);
    }
}
