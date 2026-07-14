<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ficha;
use App\Models\HorarioMateria;

class TestInstructores extends Command
{
    protected $signature = 'test:instructores';
    
    public function handle()
    {
        $fichaId = 1;
        $horarios = HorarioMateria::where('idFicha', $fichaId)
            ->with([
                'gradoMateria.materia',
                'contrato.persona',
                'asignacionSesion.contrato.persona'
            ])
            ->get();
            
        $datosPorMateria = $horarios
            ->groupBy(fn($h) => $h->gradoMateria->idMateria ?? 0)
            ->map(function ($grupo) {
                $personasDirectas = $grupo->pluck('contrato.persona')->filter();
                $personasCompartidas = $grupo->pluck('asignacionSesion')->flatten()->pluck('contrato.persona')->filter();

                return $personasDirectas->concat($personasCompartidas)->unique('id')->values()->toArray();
            });
            
        print_r($datosPorMateria->toArray());
    }
}
