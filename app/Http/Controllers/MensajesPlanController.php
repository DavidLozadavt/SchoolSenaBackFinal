<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaPlanMensaje;
use App\Models\ConfiguracionWompi;
use App\Models\MensajesPlan;
use App\Services\Mensajes\AuditoriaPlanesService;
use App\Services\Mensajes\SaldoMensajesService;
use App\Services\Pagos\WompiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Catálogo de planes de mensajes + saldo del usuario autenticado.
 *
 * Consultar el saldo y los planes activos NO requiere permiso especial: el plan
 * pertenece al usuario y cualquier usuario puede comprarlo.
 */
class MensajesPlanController extends Controller
{
    public function __construct(private AuditoriaPlanesService $auditoria)
    {
    }

    /**
     * Lista de planes. Por defecto solo los activos (vista de compra);
     * `?todos=1` devuelve también los inactivos (administración).
     */
    public function index(Request $request)
    {
        try {
            $query = MensajesPlan::query()
                ->orderBy('orden')
                ->orderBy('cantidadMensajes');

            if (!$request->boolean('todos')) {
                $query->activos();
            }

            return response()->json($query->get(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los planes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Saldo de mensajes del usuario autenticado (indicadores de la interfaz).
     */
    public function miSaldo(SaldoMensajesService $servicio)
    {
        try {
            return response()->json($servicio->resumen(auth()->id()), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el saldo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Historial de movimientos de mensajes del usuario autenticado (auditoría).
     */
    public function misMovimientos(Request $request, SaldoMensajesService $servicio)
    {
        try {
            $limite = min((int) $request->get('limite', 100), 500);

            return response()->json($servicio->movimientos(auth()->id(), $limite), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los movimientos: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Configuración general de pagos (Administrador VT).
    //
    // Fusionado aquí desde el antiguo ConfiguracionPagosController: el catálogo
    // de planes y su configuración son el mismo dominio administrativo. Toda la
    // lógica de Wompi vive en WompiService; las llaves nunca se devuelven.
    // -------------------------------------------------------------------------

    public function configuracion(WompiService $wompi)
    {
        try {
            return response()->json([
                'configuracion' => $wompi->configuracion(),
                'diagnostico'   => $wompi->diagnostico(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la configuración: ' . $e->getMessage()], 500);
        }
    }

    public function diagnosticoConfiguracion(WompiService $wompi)
    {
        try {
            return response()->json($wompi->diagnostico(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el diagnóstico: ' . $e->getMessage()], 500);
        }
    }

    public function verificarConfiguracion(WompiService $wompi)
    {
        try {
            return response()->json($wompi->verificar(), 200);
        } catch (\Exception $e) {
            Log::error('Error al verificar la configuración de pagos: ' . $e->getMessage());

            return response()->json(['error' => 'Error al verificar la configuración: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza la configuración. Las llaves solo se escriben si vienen con
     * valor: enviarlas vacías o ausentes conserva las ya guardadas (cifradas).
     */
    public function actualizarConfiguracion(Request $request, WompiService $wompi)
    {
        try {
            $data = $request->validate([
                'modo'               => ['sometimes', 'string', 'in:SANDBOX,PRODUCCION'],
                'proveedor'          => ['sometimes', 'string', 'max:30'],
                'moneda'             => ['sometimes', 'string', 'max:10'],
                'ivaPorcentaje'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'mensajesGratuitos'  => ['sometimes', 'integer', 'min:0'],
                'activo'             => ['sometimes', 'boolean'],
                'horasMaxAprobacion' => ['sometimes', 'integer', 'min:1'],
                'urlRetorno'         => ['nullable', 'string', 'max:255'],
                'urlWebhook'         => ['nullable', 'string', 'max:255'],
                'usarLlavesPropias'  => ['sometimes', 'boolean'],
                'publicKey'          => ['nullable', 'string', 'max:255'],
                'privateKey'         => ['nullable', 'string', 'max:255'],
                'integritySecret'    => ['nullable', 'string', 'max:255'],
                'eventsSecret'       => ['nullable', 'string', 'max:255'],
            ]);

            $configuracion = ConfiguracionWompi::vigente();
            $modoAnterior  = $configuracion->modo;

            // Nunca sobrescribir una llave existente con un valor vacío.
            foreach (['publicKey', 'privateKey', 'integritySecret', 'eventsSecret'] as $llave) {
                if (!array_key_exists($llave, $data) || trim((string) $data[$llave]) === '') {
                    unset($data[$llave]);
                }
            }

            $data['actualizadoPor'] = auth()->id();

            $configuracion->update($data);
            $configuracion->refresh();

            $llavesTocadas = array_values(array_intersect(
                array_keys($data),
                ['publicKey', 'privateKey', 'integritySecret', 'eventsSecret']
            ));

            $this->auditoria->registrar('CONFIGURACION_PAGOS_ACTUALIZADA', (int) auth()->id(), [
                'descripcion'    => 'Configuración general de pagos actualizada.',
                'estadoAnterior' => $modoAnterior,
                'estadoNuevo'    => $configuracion->modo,
                'observaciones'  => $llavesTocadas
                    ? 'Llaves reemplazadas: ' . implode(', ', $llavesTocadas)
                    : 'Sin cambios en las llaves.',
                'detalle'        => [
                    'moneda'             => $configuracion->moneda,
                    'ivaPorcentaje'      => $configuracion->ivaPorcentaje,
                    'mensajesGratuitos'  => $configuracion->mensajesGratuitos,
                    'activo'             => $configuracion->activo,
                    'horasMaxAprobacion' => $configuracion->horasMaxAprobacion,
                    'usarLlavesPropias'  => $configuracion->usarLlavesPropias,
                ],
            ]);

            return response()->json([
                'message'       => 'Configuración actualizada correctamente.',
                'configuracion' => $configuracion,
                'diagnostico'   => $wompi->diagnostico(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            Log::error('Error al actualizar la configuración de pagos: ' . $e->getMessage());

            return response()->json(['error' => 'Error al actualizar la configuración: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nombre'           => ['required', 'string', 'max:120', 'unique:mensajesPlanes,nombre'],
                'cantidadMensajes' => ['required', 'integer', 'min:1'],
                'precio'           => ['required', 'numeric', 'min:0'],
                'descripcion'      => ['nullable', 'string'],
                'activo'           => ['boolean'],
                'orden'            => ['nullable', 'integer', 'min:0'],
                'recomendado'      => ['boolean'],
                'color'            => ['nullable', 'string', 'max:30'],
                'etiqueta'         => ['nullable', 'string', 'max:60'],
            ]);

            $plan = MensajesPlan::create($data);

            $this->auditoria->registrar(AuditoriaPlanMensaje::PLAN_CREADO, (int) auth()->id(), [
                'descripcion'      => "Plan {$plan->nombre} creado.",
                'planId'           => $plan->id,
                'planNombre'       => $plan->nombre,
                'cantidadMensajes' => $plan->cantidadMensajes,
                'estadoNuevo'      => $plan->activo ? 'ACTIVO' : 'INACTIVO',
                'detalle'          => $data,
            ]);

            return response()->json($plan, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el plan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $plan = MensajesPlan::findOrFail($id);

            $data = $request->validate([
                'nombre'           => ['sometimes', 'string', 'max:120', 'unique:mensajesPlanes,nombre,' . $plan->id],
                'cantidadMensajes' => ['sometimes', 'integer', 'min:1'],
                'precio'           => ['sometimes', 'numeric', 'min:0'],
                'descripcion'      => ['nullable', 'string'],
                'activo'           => ['boolean'],
                'orden'            => ['nullable', 'integer', 'min:0'],
                'recomendado'      => ['boolean'],
                'color'            => ['nullable', 'string', 'max:30'],
                'etiqueta'         => ['nullable', 'string', 'max:60'],
            ]);

            $estadoAnterior = $plan->activo ? 'ACTIVO' : 'INACTIVO';

            $plan->update($data);
            $plan->refresh();

            $this->auditoria->registrar(AuditoriaPlanMensaje::PLAN_ACTUALIZADO, (int) auth()->id(), [
                'descripcion'      => "Plan {$plan->nombre} actualizado.",
                'planId'           => $plan->id,
                'planNombre'       => $plan->nombre,
                'cantidadMensajes' => $plan->cantidadMensajes,
                'estadoAnterior'   => $estadoAnterior,
                'estadoNuevo'      => $plan->activo ? 'ACTIVO' : 'INACTIVO',
                'detalle'          => $data,
            ]);

            return response()->json($plan, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el plan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $plan = MensajesPlan::findOrFail($id);

            // Un plan con compras asociadas NUNCA se elimina: solo se desactiva,
            // para no romper el histórico de solicitudes y transacciones.
            if ($plan->tieneCompras()) {
                $plan->update(['activo' => false]);

                $this->auditoria->registrar(AuditoriaPlanMensaje::PLAN_ACTUALIZADO, (int) auth()->id(), [
                    'descripcion'   => "Plan {$plan->nombre} desactivado (tiene compras asociadas, no se puede eliminar).",
                    'planId'        => $plan->id,
                    'planNombre'    => $plan->nombre,
                    'estadoNuevo'   => 'INACTIVO',
                    'observaciones' => 'Eliminación bloqueada por compras asociadas.',
                ]);

                return response()->json([
                    'message'        => 'El plan tiene compras asociadas: no puede eliminarse, se desactivó.',
                    'tieneCompras'   => true,
                    'plan'           => $plan->fresh(),
                ], 200);
            }

            $nombre = $plan->nombre;
            $planId = $plan->id;
            $plan->delete();

            $this->auditoria->registrar(AuditoriaPlanMensaje::PLAN_ACTUALIZADO, (int) auth()->id(), [
                'descripcion' => "Plan {$nombre} eliminado (sin compras asociadas).",
                'planId'      => $planId,
                'planNombre'  => $nombre,
                'estadoNuevo' => 'ELIMINADO',
            ]);

            return response()->json([
                'message'      => 'Plan eliminado correctamente.',
                'tieneCompras' => false,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el plan: ' . $e->getMessage()], 500);
        }
    }
}
