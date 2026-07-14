<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PortafolioCategoria;

class PortafolioCategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $raiz = [
            ['nombre' => '1. Programa de formacion', 'slug' => 'programa-formacion'],
            ['nombre' => '2. Proyecto formativo', 'slug' => 'proyecto-formativo'],
            ['nombre' => '3. Planeacion pedagogica', 'slug' => 'planeacion-pedagogica'],
            ['nombre' => '4. horario de la Ficha', 'slug' => 'horario-ficha'],
            ['nombre' => '5. Actas de Equipo Ejecutor', 'slug' => 'actas-equipo-ejecutor'],
            ['nombre' => '6. Guías de aprendizaje', 'slug' => 'guias-aprendizaje'],
            ['nombre' => '7. Instrumentos de evaluación', 'slug' => 'instrumentos-evaluacion'],
            ['nombre' => '8. Materia de Formacion', 'slug' => 'materia-formacion'],
            ['nombre' => '9. Juicios evaluativos', 'slug' => 'juicios-evaluativos'],
        ];

        foreach ($raiz as $i => $cat) {
            PortafolioCategoria::create([
                ...$cat,
                'orden' => $i + 1,
            ]);
        }

        $horario = PortafolioCategoria::where('slug', 'horario-ficha')->first();
        $actas = PortafolioCategoria::where('slug', 'actas-equipo-ejecutor')->first();

        foreach ([$horario, $actas] as $padre) {
            PortafolioCategoria::create([
                'nombre' => 'Primer Trimestre',
                'slug' => $padre->slug . '-t1',
                'idCategoriaPadre' => $padre->id,
                'orden' => 1,
            ]);

            PortafolioCategoria::create([
                'nombre' => 'Segundo Trimestre',
                'slug' => $padre->slug . '-t2',
                'idCategoriaPadre' => $padre->id,
                'orden' => 2,
            ]);
        }
    }
}