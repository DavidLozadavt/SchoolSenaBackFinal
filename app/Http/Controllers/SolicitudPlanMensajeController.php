<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaPlanMensaje;
use App\Models\MensajesPlan;
use App\Models\SolicitudPlanMensaje;
use App\Models\WompiTransaccion;
use App\Services\Mensajes\AuditoriaPlanesService;
use App\Services\Mensajes\NotificacionesPlanesService;
use App\Services\Mensajes\SaldoMensajesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Solicitudes de compra de planes de mensajes.
 *
 *  - store / misSolicitudes: cualquier usuario autenticado (el plan es del usuario).
 *  - index / aprobar / rechazar / comprobante: Administrador VT
 *    (permiso GESTION_SOLICITUDES_PLANES, aplicado en las rutas).
 */
class SolicitudPlanMensajeController extends Controller
{
    public function __construct(
        private AuditoriaPlanesService $auditoria,
        private NotificacionesPlanesService $notificaciones
    ) {
    }

    /**
     * Crea la solicitud del usuario autenticado, con comprobante de pago.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'planId'      => ['required', 'integer', 'exists:mensajesPlanes,id'],
                'metodoPago'  => ['required', 'string', 'max:100'],
                // El flujo principal de compra es Wompi (WompiPagoController). Este
                // endpoint se conserva como alternativa manual, por lo que el
                // comprobante pasa a ser opcional en lugar de obligatorio.
                'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);

            $plan = MensajesPlan::findOrFail($data['planId']);

            if (!$plan->activo) {
                return response()->json(['error' => 'El plan seleccionado no está activo.'], 422);
            }

            $userId = auth()->id();

            $archivo = $request->file('comprobante');
            $ruta = $archivo ? $archivo->store('comprobantes-planes', 'public') : null;

            $solicitud = SolicitudPlanMensaje::create([
                'userId'           => $userId,
                'companyId'        => $this->resolverCompanyId($userId),
                'planId'            => $plan->id,
                'planNombre'        => $plan->nombre,
                'cantidadMensajes'  => $plan->cantidadMensajes,
                'valor'             => $plan->precio,
                'metodoPago'        => $data['metodoPago'],
                'comprobanteRuta'   => $ruta,
                'comprobanteNombre' => $archivo?->getClientOriginalName(),
                'estado'            => SolicitudPlanMensaje::PENDIENTE,
            ]);

            AuditoriaPlanMensaje::create([
                'userId'      => $userId,
                'solicitudId'  => $solicitud->id,
                'accion'       => AuditoriaPlanMensaje::SOLICITUD_CREADA,
                'descripcion'  => "Solicitud del plan {$plan->nombre} ({$plan->cantidadMensajes} mensajes).",
                'realizadoPor' => $userId,
                'detalle'      => ['metodoPago' => $data['metodoPago'], 'valor' => $plan->precio],
            ]);

            $this->notificaciones->solicitudEnviada($userId, $plan->nombre, [
                'solicitudId' => $solicitud->id,
            ]);

            return response()->json([
                'message'   => 'Solicitud enviada. Un administrador la revisará en breve.',
                'solicitud' => $solicitud,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            Log::error('Error al crear la solicitud de plan: ' . $e->getMessage());

            return response()->json(['error' => 'Error al crear la solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Solicitudes del usuario autenticado.
     */
    public function misSolicitudes()
    {
        try {
            $solicitudes = SolicitudPlanMensaje::where('userId', auth()->id())
                ->orderByDesc('created_at')
                ->get();

            return response()->json($solicitudes, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las solicitudes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Listado del Administrador VT (usuario, empresa, plan, valor, estado...).
     */
    public function index(Request $request)
    {
        try {
            $query = SolicitudPlanMensaje::query()
                ->leftJoin('usuario', 'usuario.id', '=', 'solicitudesPlanMensajes.userId')
                ->leftJoin('empresa', 'empresa.id', '=', 'solicitudesPlanMensajes.companyId')
                ->leftJoin('persona', 'persona.id', '=', 'usuario.idpersona')
                // Datos del pago por Wompi (aditivo; NULL en el flujo manual).
                ->leftJoin('wompiTransacciones', 'wompiTransacciones.id', '=', 'solicitudesPlanMensajes.wompiTransaccionId')
                ->select([
                    'solicitudesPlanMensajes.*',
                    'usuario.email as usuarioEmail',
                    DB::raw("TRIM(CONCAT_WS(' ', persona.nombre1, persona.nombre2, persona.apellido1, persona.apellido2)) as usuarioNombre"),
                    'empresa.razonSocial as empresaNombre',
                    'wompiTransacciones.transactionId as pagoTransactionId',
                    'wompiTransacciones.reference as pagoReferencia',
                    'wompiTransacciones.paymentMethodType as pagoMetodo',
                    'wompiTransacciones.status as pagoEstado',
                    'wompiTransacciones.amount as pagoValor',
                    'wompiTransacciones.currency as pagoMoneda',
                    'wompiTransacciones.fechaPago as pagoFecha',
                ]);

            if ($estado = $request->get('estado')) {
                $query->where('solicitudesPlanMensajes.estado', strtoupper($estado));
            }

            if ($buscar = $request->get('buscar')) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('usuario.email', 'like', "%{$buscar}%")
                        ->orWhere('solicitudesPlanMensajes.planNombre', 'like', "%{$buscar}%")
                        ->orWhere('empresa.razonSocial', 'like', "%{$buscar}%");
                });
            }

            $solicitudes = $query->orderByDesc('solicitudesPlanMensajes.created_at')
                ->paginate((int) $request->get('per_page', 15));

            return response()->json($solicitudes, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes de planes: ' . $e->getMessage());

            return response()->json(['error' => 'Error al obtener las solicitudes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Descarga/visualiza el comprobante de pago.
     */
    public function comprobante($id)
    {
        try {
            $solicitud = SolicitudPlanMensaje::findOrFail($id);

            if (!$solicitud->comprobanteRuta || !Storage::disk('public')->exists($solicitud->comprobanteRuta)) {
                return response()->json(['error' => 'El comprobante no está disponible.'], 404);
            }

            return Storage::disk('public')->response(
                $solicitud->comprobanteRuta,
                $solicitud->comprobanteNombre ?: 'comprobante'
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el comprobante: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Aprueba la solicitud: acredita los mensajes al usuario y activa el plan.
     */
    public function aprobar($id, SaldoMensajesService $servicio)
    {
        try {
            // Idempotencia: toda la aprobación ocurre dentro de una transacción
            // con `lockForUpdate()`. Un doble clic, un reintento o dos peticiones
            // simultáneas se serializan y la segunda encuentra la solicitud ya
            // APROBADA, por lo que nunca se acredita dos veces.
            $resultado = DB::transaction(function () use ($id, $servicio) {
                $solicitud = SolicitudPlanMensaje::lockForUpdate()->find($id);

                if (!$solicitud) {
                    return ['error' => 'La solicitud no existe.', 'codigo' => 404];
                }

                // Revisables: PENDIENTE (flujo manual) y PAGO_REALIZADO (pago Wompi
                // aprobado, pendiente de que el Administrador VT active el plan).
                if (!in_array($solicitud->estado, SolicitudPlanMensaje::ESTADOS_REVISABLES, true)) {
                    return ['error' => 'La solicitud ya fue procesada.', 'codigo' => 422];
                }

                $estadoAnterior = $solicitud->estado;
                $saldoAntes     = $servicio->obtenerSaldo((int) $solicitud->userId)->mensajesDisponibles;

                $solicitud->update([
                    'estado'        => SolicitudPlanMensaje::APROBADA,
                    'revisadoPor'   => auth()->id(),
                    'fechaRevision' => now(),
                ]);

                $saldo = $servicio->acreditarPlan($solicitud, auth()->id());

                return [
                    'solicitud'      => $solicitud,
                    'saldo'          => $saldo,
                    'estadoAnterior' => $estadoAnterior,
                    'saldoAntes'     => $saldoAntes,
                ];
            });

            if (isset($resultado['error'])) {
                return response()->json(['error' => $resultado['error']], $resultado['codigo']);
            }

            /** @var SolicitudPlanMensaje $solicitud */
            $solicitud = $resultado['solicitud'];
            $saldo     = $resultado['saldo'];

            // Auditoría ampliada (Mejora 5).
            $this->auditoria->registrar(AuditoriaPlanMensaje::SOLICITUD_APROBADA, (int) $solicitud->userId, [
                'solicitudId'      => $solicitud->id,
                'descripcion'      => "Plan {$solicitud->planNombre} activado por el Administrador VT.",
                'planId'           => $solicitud->planId,
                'planNombre'       => $solicitud->planNombre,
                'cantidadMensajes' => $solicitud->cantidadMensajes,
                'mensajesAntes'    => $resultado['saldoAntes'],
                'mensajesDespues'  => $saldo->mensajesDisponibles,
                'referenciaPago'   => $solicitud->referenciaPago,
                'transactionId'    => $solicitud->wompiTransaccionId
                    ? WompiTransaccion::find($solicitud->wompiTransaccionId)?->transactionId
                    : null,
                'estadoAnterior'   => $resultado['estadoAnterior'],
                'estadoNuevo'      => SolicitudPlanMensaje::APROBADA,
                'fechaAprobacion'  => $solicitud->fechaRevision,
                'observaciones'    => 'Aprobación manual desde el módulo Solicitudes de Planes.',
            ]);

            // Notificaciones in-app (Mejora 6).
            $contexto = ['solicitudId' => $solicitud->id, 'transaccionId' => $solicitud->wompiTransaccionId];
            $this->notificaciones->solicitudAprobada((int) $solicitud->userId, $solicitud->planNombre, $contexto);
            $this->notificaciones->planActivado((int) $solicitud->userId, $solicitud->planNombre, $contexto);
            $this->notificaciones->mensajesAcreditados(
                (int) $solicitud->userId,
                (int) $solicitud->cantidadMensajes,
                (int) $saldo->mensajesDisponibles,
                $contexto
            );

            return response()->json([
                'message'   => 'Solicitud aprobada. El plan fue activado y los mensajes acreditados al usuario.',
                'solicitud' => $solicitud->fresh(),
                'saldo'     => $saldo,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al aprobar la solicitud de plan: ' . $e->getMessage());

            return response()->json(['error' => 'Error al aprobar la solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rechaza la solicitud guardando el motivo.
     */
    public function rechazar(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'motivoRechazo' => ['required', 'string', 'max:500'],
            ]);

            // Mismo bloqueo que en aprobar(): evita doble rechazo concurrente.
            $resultado = DB::transaction(function () use ($id, $data) {
                $solicitud = SolicitudPlanMensaje::lockForUpdate()->find($id);

                if (!$solicitud) {
                    return ['error' => 'La solicitud no existe.', 'codigo' => 404];
                }

                // Revisables: PENDIENTE (flujo manual) y PAGO_REALIZADO (pago Wompi
                // aprobado, pendiente de que el Administrador VT active el plan).
                if (!in_array($solicitud->estado, SolicitudPlanMensaje::ESTADOS_REVISABLES, true)) {
                    return ['error' => 'La solicitud ya fue procesada.', 'codigo' => 422];
                }

                $estadoAnterior = $solicitud->estado;

                $solicitud->update([
                    'estado'        => SolicitudPlanMensaje::RECHAZADA,
                    'motivoRechazo' => $data['motivoRechazo'],
                    'revisadoPor'   => auth()->id(),
                    'fechaRevision' => now(),
                ]);

                return ['solicitud' => $solicitud, 'estadoAnterior' => $estadoAnterior];
            });

            if (isset($resultado['error'])) {
                return response()->json(['error' => $resultado['error']], $resultado['codigo']);
            }

            /** @var SolicitudPlanMensaje $solicitud */
            $solicitud = $resultado['solicitud'];

            $this->auditoria->registrar(AuditoriaPlanMensaje::SOLICITUD_RECHAZADA, (int) $solicitud->userId, [
                'solicitudId'      => $solicitud->id,
                'descripcion'      => 'Solicitud rechazada: ' . $data['motivoRechazo'],
                'planId'           => $solicitud->planId,
                'planNombre'       => $solicitud->planNombre,
                'cantidadMensajes' => $solicitud->cantidadMensajes,
                'referenciaPago'   => $solicitud->referenciaPago,
                'estadoAnterior'   => $resultado['estadoAnterior'],
                'estadoNuevo'      => SolicitudPlanMensaje::RECHAZADA,
                'fechaAprobacion'  => $solicitud->fechaRevision,
                'observaciones'    => $data['motivoRechazo'],
                'detalle'          => ['motivoRechazo' => $data['motivoRechazo']],
            ]);

            $this->notificaciones->solicitudRechazada(
                (int) $solicitud->userId,
                $solicitud->planNombre,
                $data['motivoRechazo'],
                ['solicitudId' => $solicitud->id]
            );

            return response()->json([
                'message'   => 'Solicitud rechazada. El usuario podrá ver el motivo.',
                'solicitud' => $solicitud->fresh(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al rechazar la solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Notificaciones del usuario: solicitudes ya revisadas (aprobadas/rechazadas).
     */
    public function misNotificaciones()
    {
        try {
            $solicitudes = SolicitudPlanMensaje::where('userId', auth()->id())
                ->whereIn('estado', [SolicitudPlanMensaje::APROBADA, SolicitudPlanMensaje::RECHAZADA])
                ->orderByDesc('fechaRevision')
                ->limit(10)
                ->get();

            return response()->json($solicitudes, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las notificaciones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Empresa del usuario (solo informativo para el listado del administrador).
     */
    private function resolverCompanyId(int $userId): ?int
    {
        try {
            $companyId = DB::table('activation_company_users')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->value('company_id');

            return $companyId ? (int) $companyId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // HISTORIAL DE FACTURACIÓN (Administrador VT)
    //
    // Fusionado aquí desde el antiguo HistorialFacturacionController y su
    // servicio: mismo dominio (solicitudes de planes). SOLO LECTURA por JOINs
    // sobre tablas existentes; no crea tablas ni duplica información.
    // -------------------------------------------------------------------------

    public function historial(Request $request)
    {
        try {
            return response()->json([
                'kpis'     => $this->kpis($request),
                'registros' => $this->listado($request),
                'filtros'  => $this->filtrosAplicados($request),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al consultar el historial de facturación: ' . $e->getMessage());

            return response()->json(['error' => 'Error al consultar el historial: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Opciones para los selectores de filtro.
     */
    public function historialOpciones()
    {
        try {
            return response()->json($this->opcionesFiltros(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las opciones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reporte PDF: KPIs, filtros usados, detalle y totales.
     */
    public function historialExportPdf(Request $request)
    {
        try {
            $reporte = $this->reporte($request);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.historial_facturacion', [
                'reporte'     => $reporte,
                'generadoPor' => Auth::user()->email ?? 'Sistema',
            ])->setPaper('a4', 'landscape');

            return $pdf->stream('historial_facturacion.pdf');
        } catch (\Exception $e) {
            Log::error('Error al exportar el historial a PDF: ' . $e->getMessage());

            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reporte Excel: hoja de resumen (KPIs + filtros) y hoja de detalle.
     */
    public function historialExportExcel(Request $request)
    {
        try {
            $reporte     = $this->reporte($request);
            $generadoPor = Auth::user()->email ?? 'Sistema';

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $this->hojaResumen($spreadsheet, $reporte, $generadoPor);
            $this->hojaDetalle($spreadsheet, $reporte['detalle']);

            $spreadsheet->setActiveSheetIndex(0);

            $tmpPath = tempnam(sys_get_temp_dir(), 'facturacion') . '.xlsx';
            (new Xlsx($spreadsheet))->save($tmpPath);

            return response()->download($tmpPath, 'historial_facturacion.xlsx')->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error al exportar el historial a Excel: ' . $e->getMessage());

            return response()->json(['error' => 'Error al generar el Excel: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Construcción del Excel
    // -------------------------------------------------------------------------

    private function hojaResumen(Spreadsheet $spreadsheet, array $reporte, string $generadoPor): void
    {
        $hoja = $spreadsheet->createSheet();
        $hoja->setTitle('Resumen');

        $hoja->setCellValue('A1', 'HISTORIAL DE FACTURACIÓN');
        $hoja->mergeCells('A1:B1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $fila = 3;
        $hoja->setCellValue("A{$fila}", 'Generado el');
        $hoja->setCellValue("B{$fila}", $reporte['generadoEn']);
        $fila++;
        $hoja->setCellValue("A{$fila}", 'Generado por');
        $hoja->setCellValue("B{$fila}", $generadoPor);
        $fila += 2;

        $hoja->setCellValue("A{$fila}", 'FILTROS APLICADOS');
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
        $fila++;
        foreach ($reporte['filtros'] as $filtro) {
            $hoja->setCellValue("A{$fila}", $filtro['campo']);
            $hoja->setCellValue("B{$fila}", $filtro['valor']);
            $fila++;
        }
        $fila++;

        $hoja->setCellValue("A{$fila}", 'INDICADORES');
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
        $fila++;

        $kpis = $reporte['kpis'];
        $indicadores = [
            'Total de ventas'      => $kpis['totalVentas'],
            'Total recaudado'      => $kpis['totalRecaudado'],
            'Pagos aprobados'      => $kpis['pagosAprobados'],
            'Pagos pendientes'     => $kpis['pagosPendientes'],
            'Pagos rechazados'     => $kpis['pagosRechazados'],
            'Mensajes vendidos'    => $kpis['mensajesVendidos'],
            'Promedio por compra'  => $kpis['promedioCompra'],
        ];

        foreach ($indicadores as $etiqueta => $valor) {
            $hoja->setCellValue("A{$fila}", $etiqueta);
            $hoja->setCellValue("B{$fila}", $valor);
            $fila++;
        }

        foreach (['A', 'B'] as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }
    }

    private function hojaDetalle(Spreadsheet $spreadsheet, array $detalle): void
    {
        $hoja = $spreadsheet->createSheet();
        $hoja->setTitle('Detalle');

        $encabezados = [
            'Fecha del pago', 'Usuario', 'Correo', 'Empresa', 'Plan', 'Mensajes',
            'Valor pagado', 'Método de pago', 'Referencia Wompi', 'Transaction ID',
            'Estado del pago', 'Estado de la solicitud', 'Aprobado por', 'Fecha de aprobación',
        ];

        $hoja->fromArray($encabezados, null, 'A1');

        $estiloEncabezado = $hoja->getStyle('A1:N1');
        $estiloEncabezado->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $estiloEncabezado->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1B84FF');
        $estiloEncabezado->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $fila = 2;
        $totalValor = 0;
        $totalMensajes = 0;

        foreach ($detalle as $registro) {
            $registro = (array) $registro;

            $hoja->fromArray([
                $registro['fechaPago'] ?? '',
                $registro['usuarioNombre'] ?: '—',
                $registro['usuarioEmail'] ?? '',
                $registro['empresaNombre'] ?: '—',
                $registro['planNombre'] ?? '',
                (int) ($registro['cantidadMensajes'] ?? 0),
                (float) ($registro['valor'] ?? 0),
                $registro['pagoMetodo'] ?: ($registro['metodoPago'] ?? '—'),
                $registro['referenciaWompi'] ?: '—',
                $registro['transactionId'] ?: '—',
                $registro['estadoPago'] ?? '',
                $registro['estadoSolicitud'] ?? '',
                $registro['aprobadoPorNombre'] ?: ($registro['aprobadoPorEmail'] ?: '—'),
                $registro['fechaAprobacion'] ?: '—',
            ], null, "A{$fila}");

            $totalValor += (float) ($registro['valor'] ?? 0);
            $totalMensajes += (int) ($registro['cantidadMensajes'] ?? 0);
            $fila++;
        }

        // Totales generales.
        $hoja->setCellValue("E{$fila}", 'TOTALES');
        $hoja->setCellValue("F{$fila}", $totalMensajes);
        $hoja->setCellValue("G{$fila}", $totalValor);
        $hoja->getStyle("E{$fila}:G{$fila}")->getFont()->setBold(true);

        $hoja->getStyle("A1:N{$fila}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->setAutoFilter("A1:N" . max(1, $fila - 1));
        $hoja->freezePane('A2');

        foreach (range('A', 'N') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }
    }

    private function historialQuery(Request $request)
    {
        $query = DB::table('solicitudesPlanMensajes as s')
            ->leftJoin('wompiTransacciones as w', 'w.id', '=', 's.wompiTransaccionId')
            ->leftJoin('mensajesPlanes as p', 'p.id', '=', 's.planId')
            ->leftJoin('usuario as u', 'u.id', '=', 's.userId')
            ->leftJoin('persona as pe', 'pe.id', '=', 'u.idpersona')
            ->leftJoin('empresa as e', 'e.id', '=', 's.companyId')
            ->leftJoin('usuario as ua', 'ua.id', '=', 's.revisadoPor')
            ->leftJoin('persona as pa', 'pa.id', '=', 'ua.idpersona')
            ->select([
                's.id',
                's.created_at as fechaSolicitud',
                DB::raw('COALESCE(w.fechaPago, s.created_at) as fechaPago'),
                's.userId',
                'u.email as usuarioEmail',
                DB::raw("TRIM(CONCAT_WS(' ', pe.nombre1, pe.nombre2, pe.apellido1, pe.apellido2)) as usuarioNombre"),
                's.companyId',
                'e.razonSocial as empresaNombre',
                's.planId',
                's.planNombre',
                DB::raw('COALESCE(p.nombre, s.planNombre) as planActual'),
                's.cantidadMensajes',
                's.valor',
                's.metodoPago',
                'w.paymentMethodType as pagoMetodo',
                'w.reference as referenciaWompi',
                'w.transactionId as transactionId',
                DB::raw("COALESCE(w.status, s.estadoPago, 'MANUAL') as estadoPago"),
                'w.currency as moneda',
                's.estado as estadoSolicitud',
                's.revisadoPor',
                'ua.email as aprobadoPorEmail',
                DB::raw("TRIM(CONCAT_WS(' ', pa.nombre1, pa.nombre2, pa.apellido1, pa.apellido2)) as aprobadoPorNombre"),
                's.fechaRevision as fechaAprobacion',
                's.motivoRechazo',
            ]);

        $this->aplicarFiltrosHistorial($query, $request);

        return $query;
    }

    /**
     * Listado paginado y ordenado.
     */
    private function listado(Request $request)
    {
        $columnasOrdenables = [
            'fechaPago'        => DB::raw('COALESCE(w.fechaPago, s.created_at)'),
            'usuarioNombre'    => 'u.email',
            'empresaNombre'    => 'e.razonSocial',
            'planNombre'       => 's.planNombre',
            'cantidadMensajes' => 's.cantidadMensajes',
            'valor'            => 's.valor',
            'estadoPago'       => 'w.status',
            'estadoSolicitud'  => 's.estado',
        ];

        $orden    = $request->get('orden', 'fechaPago');
        $sentido  = strtolower($request->get('sentido', 'desc')) === 'asc' ? 'asc' : 'desc';
        $columna  = $columnasOrdenables[$orden] ?? $columnasOrdenables['fechaPago'];

        return $this->historialQuery($request)
            ->orderBy($columna, $sentido)
            ->paginate(min((int) $request->get('per_page', 20), 200));
    }

    /**
     * KPIs del conjunto filtrado (sin paginar).
     */
    private function kpis(Request $request): array
    {
        $base = $this->historialQuery($request);

        $filas = DB::query()->fromSub($base, 'r')->select([
            DB::raw('COUNT(*) as totalVentas'),
            DB::raw("SUM(CASE WHEN estadoSolicitud = 'APROBADA' THEN valor ELSE 0 END) as totalRecaudado"),
            DB::raw("SUM(CASE WHEN estadoPago = 'APPROVED' THEN 1 ELSE 0 END) as pagosAprobados"),
            DB::raw("SUM(CASE WHEN estadoPago IN ('PENDING','MANUAL') THEN 1 ELSE 0 END) as pagosPendientes"),
            DB::raw("SUM(CASE WHEN estadoPago IN ('DECLINED','VOIDED','ERROR') THEN 1 ELSE 0 END) as pagosRechazados"),
            DB::raw('SUM(cantidadMensajes) as mensajesVendidos'),
        ])->first();

        $totalVentas    = (int) ($filas->totalVentas ?? 0);
        $totalRecaudado = (float) ($filas->totalRecaudado ?? 0);

        return [
            'totalVentas'      => $totalVentas,
            'totalRecaudado'   => $totalRecaudado,
            'pagosAprobados'   => (int) ($filas->pagosAprobados ?? 0),
            'pagosPendientes'  => (int) ($filas->pagosPendientes ?? 0),
            'pagosRechazados'  => (int) ($filas->pagosRechazados ?? 0),
            'mensajesVendidos' => (int) ($filas->mensajesVendidos ?? 0),
            'promedioCompra'   => $totalVentas > 0 ? round($totalRecaudado / $totalVentas, 2) : 0,
        ];
    }

    /**
     * Reporte completo usado por las exportaciones.
     */
    private function reporte(Request $request): array
    {
        return [
            'kpis'          => $this->kpis($request),
            'detalle'       => $this->historialQuery($request)
                ->orderBy(DB::raw('COALESCE(w.fechaPago, s.created_at)'), 'desc')
                ->limit(5000)
                ->get()
                ->toArray(),
            'filtros'       => $this->filtrosAplicados($request),
            'generadoEn'    => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Opciones para los selectores de filtro.
     */
    private function opcionesFiltros(): array
    {
        return [
            'planes' => DB::table('mensajesPlanes')->orderBy('nombre')
                ->get(['id', 'nombre'])->toArray(),

            'empresas' => DB::table('solicitudesPlanMensajes as s')
                ->join('empresa as e', 'e.id', '=', 's.companyId')
                ->distinct()
                ->orderBy('e.razonSocial')
                ->get(['e.id', 'e.razonSocial as nombre'])->toArray(),

            'metodosPago' => DB::table('wompiTransacciones')
                ->whereNotNull('paymentMethodType')
                ->distinct()
                ->orderBy('paymentMethodType')
                ->pluck('paymentMethodType')->toArray(),

            'estadosPago'      => ['APPROVED', 'PENDING', 'DECLINED', 'VOIDED', 'ERROR', 'MANUAL'],
            'estadosSolicitud' => ['PENDIENTE', 'PAGO_REALIZADO', 'APROBADA', 'RECHAZADA'],
        ];
    }

    /**
     * Resumen legible de los filtros usados (se imprime en los reportes).
     */
    private function filtrosAplicados(Request $request): array
    {
        $etiquetas = [
            'desde'           => 'Desde',
            'hasta'           => 'Hasta',
            'usuario'         => 'Usuario',
            'empresaId'       => 'Empresa',
            'planId'          => 'Plan',
            'estadoPago'      => 'Estado del pago',
            'estadoSolicitud' => 'Estado de la solicitud',
            'metodoPago'      => 'Método de pago',
            'buscar'          => 'Búsqueda',
        ];

        $aplicados = [];
        foreach ($etiquetas as $campo => $etiqueta) {
            $valor = $request->get($campo);
            if ($valor !== null && $valor !== '') {
                $aplicados[] = ['campo' => $etiqueta, 'valor' => (string) $valor];
            }
        }

        return $aplicados ?: [['campo' => 'Filtros', 'valor' => 'Ninguno (todos los registros)']];
    }

    // -------------------------------------------------------------------------

    private function aplicarFiltrosHistorial($query, Request $request): void
    {
        if ($desde = $request->get('desde')) {
            $query->whereRaw('COALESCE(w.fechaPago, s.created_at) >= ?', [
                \Carbon\Carbon::parse($desde)->startOfDay(),
            ]);
        }

        if ($hasta = $request->get('hasta')) {
            $query->whereRaw('COALESCE(w.fechaPago, s.created_at) <= ?', [
                \Carbon\Carbon::parse($hasta)->endOfDay(),
            ]);
        }

        if ($usuario = $request->get('usuario')) {
            $query->where(function ($q) use ($usuario) {
                $q->where('u.email', 'like', "%{$usuario}%")
                    ->orWhere('pe.nombre1', 'like', "%{$usuario}%")
                    ->orWhere('pe.apellido1', 'like', "%{$usuario}%");
            });
        }

        if ($empresaId = $request->get('empresaId')) {
            $query->where('s.companyId', $empresaId);
        }

        if ($planId = $request->get('planId')) {
            $query->where('s.planId', $planId);
        }

        if ($estadoPago = $request->get('estadoPago')) {
            if (strtoupper($estadoPago) === 'MANUAL') {
                $query->whereNull('w.id');
            } else {
                $query->where('w.status', strtoupper($estadoPago));
            }
        }

        if ($estadoSolicitud = $request->get('estadoSolicitud')) {
            $query->where('s.estado', strtoupper($estadoSolicitud));
        }

        if ($metodoPago = $request->get('metodoPago')) {
            $query->where('w.paymentMethodType', $metodoPago);
        }

        if ($buscar = $request->get('buscar')) {
            $query->where(function ($q) use ($buscar) {
                $q->where('u.email', 'like', "%{$buscar}%")
                    ->orWhere('e.razonSocial', 'like', "%{$buscar}%")
                    ->orWhere('s.planNombre', 'like', "%{$buscar}%")
                    ->orWhere('w.reference', 'like', "%{$buscar}%")
                    ->orWhere('w.transactionId', 'like', "%{$buscar}%");
            });
        }
    }
}
