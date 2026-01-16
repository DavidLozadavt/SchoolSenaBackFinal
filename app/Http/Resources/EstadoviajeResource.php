<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EstadoviajeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id'      => $this->id,
            'estado'  => $this->estado,
            'viaje'   => [
                'id'        => $this->viaje->id ?? null,
                'estado'    => $this->viaje->estado ?? null,
                'vehiculo'  => $this->viaje->idVehiculo ?? null,
                'conductor' => $this->viaje->idConductor ?? null,
                'tickets'   => $this->viaje->tickets->map(function ($ticket) {
                    return [
                        'id'       => $ticket->id,
                        'cantidad' => $ticket->cantidad,
                        'precio'   => $ticket->ruta?->precio,
                        'ruta'     => [
                            'descripcion'   => $ticket->ruta?->descripcion,
                            'distancia'     => $ticket->ruta?->distancia,
                            'tiempo'        => $ticket->ruta?->tiempoEstimado,
                            'lugar'         => $ticket->ruta?->lugar?->nombre,
                            'ciudadOrigen'  => $ticket->ruta?->ciudadOrigen?->nombre,
                            'ciudadDestino' => $ticket->ruta?->ciudadDestino?->nombre,
                        ]
                    ];
                })
            ]
        ];
    }
}
