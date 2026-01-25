<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AreaConocimientoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            ['nombreAreaConocimiento' => 'Matemáticas'],
            ['nombreAreaConocimiento' => 'Ciencias Naturales'],
            ['nombreAreaConocimiento' => 'Lenguaje y Comunicación'],
            ['nombreAreaConocimiento' => 'Inglés'],
            ['nombreAreaConocimiento' => 'Ciencias Sociales'],
            ['nombreAreaConocimiento' => 'Tecnología e Informática'],
            ['nombreAreaConocimiento' => 'Artes'],
            ['nombreAreaConocimiento' => 'Educación Física'],
            ['nombreAreaConocimiento' => 'Filosofía'],
            ['nombreAreaConocimiento' => 'Química'],
        ];

        \DB::table('area_conocimiento')->insert($data);
    }
}
