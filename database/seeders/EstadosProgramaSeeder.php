<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadosProgramaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
        ['nombre' => 'POR APROBAR'],
        ['nombre' => 'APROBADO'],
        ['nombre' => 'DESABROBADO'],
       
    ];

    \DB::table('estadoPrograma')->insert($data);
    }
}
