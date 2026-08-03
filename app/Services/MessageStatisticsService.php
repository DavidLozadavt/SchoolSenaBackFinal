<?php

namespace App\Services;

use App\Models\WhatsappMensajeHistorial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de mensajes WhatsApp del módulo Seguimiento de Aspirantes.
 *
 * Lee del historial append-only `whatsapp_mensajes_historial` — un registro
 * por cada mensaje enviado (nunca se sobrescribe), a diferencia del snapshot
 * en `seguimientoAspirantes` que solo guarda el último envío. Esto permite
 * que las estadísticas reflejen reenvíos y múltiples campañas a un mismo
 * aspirante. Solo lectura: no modifica ninguna fila.
 */
class MessageStatisticsService
{
    /**
     * Query base con join a seguimientoAspirantes (para filtrar/mostrar
     * programa, ficha, centro, nombre, celular).
     */
    private function baseQuery(Request $request): Builder
    {
        $query = WhatsappMensajeHistorial::query()
            ->join('seguimientoAspirantes', 'seguimientoAspirantes.id', '=', 'whatsapp_mensajes_historial.seguimientoAspiranteId');

        if ($request->filled('fecha_desde')) {
            $query->whereDate('whatsapp_mensajes_historial.fecha_envio', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('whatsapp_mensajes_historial.fecha_envio', '<=', $request->fecha_hasta);
        }
        if ($request->filled('programa')) {
            $query->where('seguimientoAspirantes.programa', $request->programa);
        }
        if ($request->filled('ficha')) {
            $query->where('seguimientoAspirantes.ficha', $request->ficha);
        }
        if ($request->filled('centro_formacion')) {
            $query->where('seguimientoAspirantes.centro_formacion', $request->centro_formacion);
        }
        if ($request->filled('estado')) {
            $query->where('whatsapp_mensajes_historial.estado', $request->estado);
        }
        if ($request->filled('plantilla')) {
            $query->where('whatsapp_mensajes_historial.template', $request->plantilla);
        }

        return $query;
    }

    public function kpis(Request $request): array
    {
        $query = $this->baseQuery($request);

        return [
            'enviados'   => (clone $query)->count(),
            'entregados' => (clone $query)->whereIn('whatsapp_mensajes_historial.estado', ['delivered', 'read'])->count(),
            'leidos'     => (clone $query)->where('whatsapp_mensajes_historial.estado', 'read')->count(),
            'errores'    => (clone $query)->where('whatsapp_mensajes_historial.estado', 'failed')->count(),
            // Fase 2: visibilidad de plantillas/conversaciones (preparación
            // para facturación futura, sin calcular costos todavía).
            // "Distintas" y no "mensajes con plantilla": mismo criterio que
            // totalConversaciones (agrupado por identificador único).
            'totalPlantillasEnviadas' => (clone $query)->whereNotNull('whatsapp_mensajes_historial.template')
                ->distinct('whatsapp_mensajes_historial.template')
                ->count('whatsapp_mensajes_historial.template'),
            'totalConversaciones' => (clone $query)->whereNotNull('whatsapp_mensajes_historial.conversationId')
                ->distinct('whatsapp_mensajes_historial.conversationId')
                ->count('whatsapp_mensajes_historial.conversationId'),
        ];
    }

    public function porDia(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(DB::raw('DATE(whatsapp_mensajes_historial.fecha_envio) as fecha'), DB::raw('COUNT(*) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($r) => ['fecha' => $r->fecha, 'total' => (int) $r->total])
            ->all();
    }

    public function porMes(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(DB::raw("DATE_FORMAT(whatsapp_mensajes_historial.fecha_envio, '%Y-%m') as mes"), DB::raw('COUNT(*) as total'))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->map(fn ($r) => ['mes' => $r->mes, 'total' => (int) $r->total])
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

    public function porCentro(Request $request): array
    {
        return $this->agruparPor($request, 'centro_formacion');
    }

    public function plantillasMasUsadas(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(DB::raw('COALESCE(whatsapp_mensajes_historial.template, "Sin registrar") as plantilla'), DB::raw('COUNT(*) as total'))
            ->groupBy('plantilla')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['plantilla' => $r->plantilla, 'total' => (int) $r->total])
            ->all();
    }

    public function estadosDistribucion(Request $request): array
    {
        return $this->baseQuery($request)
            ->select('whatsapp_mensajes_historial.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('whatsapp_mensajes_historial.estado')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['estado' => $r->estado, 'total' => (int) $r->total])
            ->all();
    }

    private function agruparPor(Request $request, string $columna): array
    {
        return $this->baseQuery($request)
            ->select("seguimientoAspirantes.{$columna}", DB::raw('COUNT(*) as total'))
            ->groupBy("seguimientoAspirantes.{$columna}")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [$columna => $r->{$columna}, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * Variantes "resumen" (mensajes + conversaciones) usadas SOLO por los
     * reportes exportables (Excel/PDF). No reemplazan a porPrograma()/
     * porCentro()/plantillasMasUsadas(), que siguen igual para el dashboard.
     */
    public function resumenPorPlantilla(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(
                DB::raw('COALESCE(whatsapp_mensajes_historial.template, "Sin registrar") as plantilla'),
                DB::raw('COUNT(*) as mensajes'),
                DB::raw('COUNT(DISTINCT whatsapp_mensajes_historial.conversationId) as conversaciones')
            )
            ->groupBy('plantilla')
            ->orderByDesc('mensajes')
            ->get()
            ->map(fn ($r) => ['plantilla' => $r->plantilla, 'mensajes' => (int) $r->mensajes, 'conversaciones' => (int) $r->conversaciones])
            ->all();
    }

    public function resumenPorPrograma(Request $request): array
    {
        return $this->resumenAgrupadoPor($request, 'programa');
    }

    public function resumenPorCentro(Request $request): array
    {
        return $this->resumenAgrupadoPor($request, 'centro_formacion');
    }

    public function resumenPorFicha(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(
                'seguimientoAspirantes.ficha',
                'seguimientoAspirantes.programa',
                DB::raw('COUNT(*) as mensajes')
            )
            ->groupBy('seguimientoAspirantes.ficha', 'seguimientoAspirantes.programa')
            ->orderByDesc('mensajes')
            ->get()
            ->map(fn ($r) => ['ficha' => $r->ficha, 'programa' => $r->programa, 'mensajes' => (int) $r->mensajes])
            ->all();
    }

    public function resumenPorFecha(Request $request): array
    {
        return $this->baseQuery($request)
            ->select(
                DB::raw('DATE(whatsapp_mensajes_historial.fecha_envio) as fecha'),
                DB::raw('COUNT(*) as mensajes'),
                DB::raw('COUNT(DISTINCT whatsapp_mensajes_historial.conversationId) as conversaciones')
            )
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($r) => ['fecha' => $r->fecha, 'mensajes' => (int) $r->mensajes, 'conversaciones' => (int) $r->conversaciones])
            ->all();
    }

    private function resumenAgrupadoPor(Request $request, string $columna): array
    {
        return $this->baseQuery($request)
            ->select(
                "seguimientoAspirantes.{$columna}",
                DB::raw('COUNT(*) as mensajes'),
                DB::raw('COUNT(DISTINCT whatsapp_mensajes_historial.conversationId) as conversaciones')
            )
            ->groupBy("seguimientoAspirantes.{$columna}")
            ->orderByDesc('mensajes')
            ->get()
            ->map(fn ($r) => [$columna => $r->{$columna}, 'mensajes' => (int) $r->mensajes, 'conversaciones' => (int) $r->conversaciones])
            ->all();
    }

    public function listado(Request $request)
    {
        $ordenables = ['fecha_envio', 'estado', 'template', 'nombre', 'programa', 'ficha'];
        $sortBy = in_array($request->get('sort_by'), $ordenables, true) ? $request->get('sort_by') : 'fecha_envio';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';

        $columnaOrden = in_array($sortBy, ['nombre', 'programa', 'ficha'], true)
            ? "seguimientoAspirantes.{$sortBy}"
            : "whatsapp_mensajes_historial.{$sortBy}";

        return $this->baseQuery($request)
            ->select([
                'whatsapp_mensajes_historial.id',
                'whatsapp_mensajes_historial.fecha_envio',
                'whatsapp_mensajes_historial.template',
                'whatsapp_mensajes_historial.estado',
                'whatsapp_mensajes_historial.waMessageId',
                'whatsapp_mensajes_historial.esMigrado',
                'seguimientoAspirantes.nombre',
                'seguimientoAspirantes.apellido',
                'seguimientoAspirantes.celular',
                'seguimientoAspirantes.programa',
                'seguimientoAspirantes.ficha',
                'seguimientoAspirantes.centro_formacion',
            ])
            ->orderBy($columnaOrden, $sortDir)
            ->paginate($request->get('per_page', 20));
    }

    /**
     * Reporte agregado para exportación (Excel/PDF).
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
            'porCentro' => $this->porCentro($request),
            'porFicha' => $this->porFicha($request),
            'porFecha' => $this->porDia($request),
            'estadosDistribucion' => $this->estadosDistribucion($request),
            'detalleFacturacion' => $this->detalleFacturacion($request),
            // Variantes con conteo de conversaciones, para el reporte ejecutivo.
            'resumenPorPlantilla' => $this->resumenPorPlantilla($request),
            'resumenPorPrograma' => $this->resumenPorPrograma($request),
            'resumenPorCentro' => $this->resumenPorCentro($request),
            'resumenPorFicha' => $this->resumenPorFicha($request),
            'resumenPorFecha' => $this->resumenPorFecha($request),
        ];
    }

    /**
     * Detalle fila por mensaje con los campos que una futura calculadora de
     * facturación necesitará (conversationId, template, phone_number_id,
     * company_id) — sin calcular ningún costo aquí, solo se deja disponible.
     */
    public function detalleFacturacion(Request $request): array
    {
        return $this->baseQuery($request)
            ->select([
                'whatsapp_mensajes_historial.fecha_envio',
                'whatsapp_mensajes_historial.company_id',
                'whatsapp_mensajes_historial.phone_number_id',
                'whatsapp_mensajes_historial.waMessageId',
                'whatsapp_mensajes_historial.conversationId',
                'whatsapp_mensajes_historial.template',
                'whatsapp_mensajes_historial.estado',
                'seguimientoAspirantes.nombre',
                'seguimientoAspirantes.apellido',
                'seguimientoAspirantes.celular',
                'seguimientoAspirantes.programa',
                'seguimientoAspirantes.ficha',
                'seguimientoAspirantes.centro_formacion',
            ])
            ->orderByDesc('whatsapp_mensajes_historial.fecha_envio')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->all();
    }
}
