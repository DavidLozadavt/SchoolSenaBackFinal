<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AperturarProgramaResource extends JsonResource
{
    public function toArray($request)
    {
        return array_filter([
            'id' => $this->id,
            'nombre' => $this->nombre,
            'observacion' => $this->observacion,
            'estado' => $this->estado,

            'fechaInicialClases' => $this->fechaInicialClases,
            'fechaFinalClases' => $this->fechaFinalClases,
            'fechaInicialInscripciones' => $this->fechaInicialInscripciones,
            'fechaFinalInscripciones' => $this->fechaFinalInscripciones,
            'fechaInicialMatriculas' => $this->fechaInicialMatriculas,
            'fechaFinalMatriculas' => $this->fechaFinalMatriculas,
            'fechaInicialPlanMejoramiento' => $this->fechaInicialPlanMejoramiento,
            'fechaFinalPlanMejoramiento' => $this->fechaFinalPlanMejoramiento,

            'tipoCalificacion' => $this->tipoCalificacion,

            'pension' => $this->pension,
            'valorPension' => $this->valorPension,
            'diasMoraMatricula' => $this->diasMoraMatricula,
            'porcentajeMoraPension' => $this->porcentajeMoraPension,
            'diaCobro' => $this->diaCobro,

            'periodo' => $this->whenLoaded('periodo'),
            'programa' => $this->whenLoaded('programa'),
            'sede' => $this->whenLoaded('sede'),
            'jornada' => $this->whenLoaded('jornada'),
            'grado' => $this->whenLoaded('grado'),
            'cortes' => $this->whenLoaded('cortes', function () {
                return $this->cortes->map(function ($corte) {
                    return [
                        'id' => $corte->id,
                        'numero' => $corte->numero,
                        'fechaInicial' => $corte->fechaInicial,
                        'fechaFinal' => $corte->fechaFinal,
                        'porcentaje' => $corte->porcentaje,
                    ];
                });
            }),
        ], fn($value) => !is_null($value));
    }
}
