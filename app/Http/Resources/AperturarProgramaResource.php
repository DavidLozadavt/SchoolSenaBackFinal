<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AperturarProgramaResource extends JsonResource
{
    public function toArray($request)
    {
        return array_filter([
            'id' => $this->id,
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

            'periodo' => $this->whenLoaded('periodo'),
            'programa' => $this->whenLoaded('programa'),
            'sede' => $this->whenLoaded('sede'),
        ], fn ($value) => !is_null($value));
    }
}
