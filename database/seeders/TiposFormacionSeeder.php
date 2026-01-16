<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TiposFormacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
        ['nombreTipoFormacion' => 'PRESENCIAL'],
        ['nombreTipoFormacion' => 'VIRTUAL'],
        ['nombreTipoFormacion' => 'HIBRIDA'],
       
    ];

    \DB::table('tipoFormacion')->insert($data);
    }
}
