<?php

namespace App\Services;

use App\Models\SeguimientoAspirante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de mensajes WhatsApp del módulo Seguimiento de Aspirantes.
 *
 * Reutiliza EXCLUSIVAMENTE el snapshot ya guardado en `seguimientoAspirantes`
 * (estadoEnvio, waMessageId, errorEnvio, ultimo_envio, cantidad_envios,
 * ultimaPlantilla) — no crea tabla nueva ni duplica información. Al ser un
 * snapshot por aspirante (no un log histórico de cada envío individual),
 * "mensajes enviados por día" refleja la fecha del ÚLTIMO envío de cada
 * aspirante, no un historial completo de reintentos.
 *
 * Solo lectura: no modifica ninguna fila de seguimientoAspirantes.
 */
class MessageStatisticsService
{
    /**
     * Query base: solo aspirantes que ya tuvieron al menos un envío de WhatsApp.
     */
    private function baseQuery(Request $request): Builder
    {
        $query = SeguimientoAspirante::query()->whereNotNull('estadoEnvio');

        if ($request->filled('fecha_desde')) {
            $query->whereDate('ultimo_envio', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('ultimo_envio', '<=', $request->fecha_hasta);
        }
        if ($request->filled('programa')) {
            $query->where('programa', $request->programa);
        }
        if ($request->filled('ficha')) {
            $query->where('ficha', $request->ficha);
        }
        if ($request->filled('centro_formacion')) {
            $query->where('centro_formacion', $request->centro_formacion);
        }
        if ($request->filled('estado')) {
            $query->where('estadoEnvio', $request->estado);
        }
        if ($request->filled('plantilla')) {
            $query->where('ultimaPlantilla', $request->plantilla);
        }

        return $query;
    }

    /**
     * KPIs: enviados, entregados, leídos, con error.
     */
    public function kpis(Request $request): array
    {
        $query = $this->baseQuery($request);

        return [
            'enviados'   => (clone $query)->count(),
            'entregados' => (clone $query)->whereIn('estadoEnvio', ['delivered', 'read'])->count(),
            'leidos'     => (clone $query)->where('estadoEnvio', 'read')->count(),
            'errores'    => (clone $query)->where('estadoEnvio', 'failed')->count(),
        ];
    }

    public function porDia(Request $request): array
    {
        return $this->baseQuery($request)
            ->whereNotNull('ultimo_envio')
            ->select(DB::raw('DATE(ultimo_envio) as fecha'), DB::raw('COUNT(*) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($r) => ['fecha' => $r->fecha, 'total' => (int) $r->total])
            ->all();
    }

    public function porPrograma(Request $request): array
    {
        return $this->agruparPor($request, 'programa');
    }

    public function porFicha(Request $request): array
    {
        return $this->agruparPor($request, 'ficha');
    }

    public function plantillasMasUsadas(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(DB::raw('COALESCE(ultimaPlantilla, "Sin registrar") as plantilla'), DB::raw('COUNT(*) as total'))
            ->groupBy('plantilla')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['plantilla' => $r->plantilla, 'total' => (int) $r->total])
            ->all();
    }

    public function estadosDistribucion(Request $request): array
    {
        return $this->baseQuery($request)
            ->select('estadoEnvio', DB::raw('COUNT(*) as total'))
            ->groupBy('estadoEnvio')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['estado' => $r->estadoEnvio, 'total' => (int) $r->total])
            ->all();
    }

    private function agruparPor(Request $request, string $columna): array
    {
        return $this->baseQuery($request)
            ->select($columna, DB::raw('COUNT(*) as total'))
            ->groupBy($columna)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [$columna => $r->{$columna}, 'total' => (int) $r->total])
            ->all();
    }

    public function listado(Request $request)
    {
        return $this->baseQuery($request)
            ->select([
                'id', 'nombre', 'apellido', 'celular', 'programa', 'ficha', 'centro_formacion',
                'ultimaPlantilla', 'estadoEnvio', 'errorEnvio', 'waMessageId', 'ultimo_envio',
            ])
            ->orderByDesc('ultimo_envio')
            ->paginate($request->get('per_page', 20));
    }

    /**
     * Reporte agregado para exportación (Excel/PDF): totales, detalle por
     * plantilla y por programa, dentro del periodo consultado.
     */
    public function reporte(Request $request): array
    {
        return [
            'periodo' => [
                'desde' => $request->get('fecha_desde'),
                'hasta' => $request->get('fecha_hasta'),
            ],
            'totales' => $this->kpis($request),
            'porPlantilla' => $this->plantillasMasUsadas($request),
            'porPrograma' => $this->porPrograma($request),
        ];
    }
}
