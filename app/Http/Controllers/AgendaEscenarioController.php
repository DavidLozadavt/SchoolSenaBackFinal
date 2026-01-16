<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\AsignacionCarritoProducto;
use App\Models\AsignacionResponsableServicio;
use App\Models\DetalleServicio;
use App\Models\ConfiguracionRepeticion;
use App\Models\Escenario;
use App\Models\Person;
use App\Models\PrestacionServicio;
use App\Models\ResponsableServicio;
use App\Models\Servicio;
use App\Models\ShoppingCart;
use App\Models\Tercero;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Pusher\Pusher;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; 
use Exception;
use App\Jobs\GenerarCorreoGeneral;
class AgendaEscenarioController extends Controller
{
        /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
   public function index(Request $request)
{
    $idCompany = KeyUtil::idCompany();
    $filtroEstado = $request->query('estado'); //YM para filtrar las reservas escenario

    $agendas = Agenda::where('idCompany', $idCompany)
        ->where('tipo', 'ESCENARIO')
        ->with('asignacionesResponsables.escenario', 'asignacionesResponsables.servicio', 'asignacionesResponsables.cliente', 'configuracionRepeticion');

    if (!empty($filtroEstado)) {
        $agendas->where('estado', $filtroEstado);
    }
    
    return response()->json($agendas->get());
}





    public function agendasByUser()
    {
        $userId = KeyUtil::user()->id;
        $usuario = User::find($userId);
        $idPersona = $usuario->idpersona;

        $agendas = Agenda::whereHas('asignacionesResponsables', function ($query) use ($idPersona) {
            $query->where('idResponsable', $idPersona)
                ->where('estado', '!=', 'COMPLETADO');
        })
            ->with('asignacionesResponsables.responsable', 'asignacionesResponsables.servicio', 'asignacionesResponsables.cliente')
            ->get();

        return response()->json($agendas);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

  public function store(Request $request)
{
    $request->validate([
        'date' => 'required|date',
        'endDate' => 'nullable|date|after_or_equal:date', 
        'time' => 'required|date_format:H:i',
        'horaFinal' => 'required|date_format:H:i', 
        'idServicio' => 'required|integer',
        'idTercero' => 'required|integer',
        'idEscenario' => 'required|integer',
        'comentario' => 'nullable|string',

        'recurrenciaTipo' => 'required|string|in:NO_REPETIR,DIARIO,SEMANAL,QUINCENAL,MENSUAL',
        'fechaFinRepeticion' => 'nullable|required_if:recurrenciaTipo,DIARIO,SEMANAL,QUINCENAL,MENSUAL|date|after_or_equal:date',
    ]);
    
    $idCompany = KeyUtil::idCompany();
    $idUser = KeyUtil::user()->id;
    $data = $request->all();

    $fechaInicial = Carbon::parse($request->date)->format('Y-m-d');
    $data['fechaFinal'] = $request->input('endDate') ?? $request->input('date');
 
    $existingAgenda = Agenda::where('fechaInicial', $fechaInicial)
        ->where('horaInicial', $request->time)
        ->whereNotIn('estado', ['COMPLETADO', 'CANCELADO'])
        ->whereHas('asignacionResponsableServicio', function ($query) use ($request) {
            $query->where('idEscenario', $request->idEscenario);
        })
        ->exists();

    if ($existingAgenda) {
        return response()->json([
            'message' => 'El escenario ya tiene una reserva en la misma fecha y hora inicial, cambie la hora.',
        ], 400);
    }

    DB::beginTransaction();

    try {
        $primeraAgenda = $this->createAgendas($data, $idCompany, $idUser);

        DB::commit();

        $asignacion = $primeraAgenda->asignacionResponsableServicio;
        $servicio = Servicio::find($asignacion->idServicio);
        $cliente = Tercero::find($asignacion->idCliente);
        $escenario = Escenario::find($asignacion->idEscenario);

        // AJUSTE WHATSAPP: ENVIAR CONFIRMACIÓN 
        $whatsappStatus = 'No intentada'; 
        
        $nombreCliente = $cliente->nombre ?? 'Cliente';
        $nombreEscenarioServicio = $escenario->nombre . ' - ' . $servicio->nombre; 
        
        $fechaHoraAgenda = Carbon::parse($primeraAgenda->fechaInicial)->format('d/m/Y') 
                         . ' a las ' 
                         . Carbon::parse($primeraAgenda->horaInicial)->format('h:i a');

        $numeroSinPrefijo = $cliente->telefono;
        if (strlen($numeroSinPrefijo) == 10) { 
            $telefonoCliente = '57' . $numeroSinPrefijo;
        } else {
            $telefonoCliente = $numeroSinPrefijo; 
        }
        
        if ($telefonoCliente) {
            try {
               $whatsappRequest = Request::create('/api/enviar-whatsapp', 'POST', [
                    'telefono' => $telefonoCliente,
                    'template_name' => 'reserva_confirmada_nexi', 
                    'language_code' => 'es_CO', 
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $nombreCliente],              
                                ['type' => 'text', 'text' => $nombreEscenarioServicio],    
                                ['type' => 'text', 'text' => $fechaHoraAgenda],            
                            ]
                        ]
                    ]
                ]);
                
                $whatsappController = new WhatsAppController();
                $whatsappResult = $whatsappController->enviarMensaje($whatsappRequest);

                if ($whatsappResult['success'] ?? false) {
                    $whatsappStatus = 'Notificación WhatsApp enviada con éxito.';
                } else {
                    $whatsappStatus = 'FALLO WHATSAPP: ' . ($whatsappResult['message'] ?? 'Error desconocido');
                }

            } catch (Exception $e) {
                \Log::error('Error crítico al procesar el WhatsAppController (Escenario): ' . $e->getMessage());
                $whatsappStatus = 'Error CRÍTICO al procesar el envío de WhatsApp: ' . $e->getMessage();
            }
        } else {
            $whatsappStatus = 'No se envió: Número de teléfono no válido o no encontrado.';
        }

          //  NUEVA NOTIFICACIÓN POR CORREO: CREACIÓN
        try {
             $isRecurrent = $request->input('recurrenciaTipo') !== 'NO_REPETIR';
             $this->sendReservaEscenarioNotification(
                $cliente, 
                $servicio, 
                $escenario, 
                $primeraAgenda, 
                'CREADA', 
                $isRecurrent ? $primeraAgenda->configuracionRepeticion : null
            );
        } catch (Exception $e) {
            \Log::error('Error enviando correo de CREACIÓN de reserva: ' . $e->getMessage());
        }


        $pusherData = [
            'idAgenda' => $primeraAgenda->id,
            'estado' => 'AGENDADO',
            'titulo' => 'Nueva agenda(s) creada',
            'mensaje' => 'Se ha creado la serie de agenda(s) correctamente',
            'fecha' => now()->toDateTimeString(),
            'agenda' => $primeraAgenda,
            'asignacion' => $asignacion,
            'servicio' => $servicio,
            'cliente' => $cliente,
            'escenario' => $escenario,
            'is_new' => true
        ];

        $this->enviarNotificacionPusher('MobileUrban', 'nueva-agenda-creada', $pusherData);

        return response()->json([
            'agenda' => $primeraAgenda,
            'pusher_sent' => true,
            'whatsapp_status' => $whatsappStatus,
            'message' => 'Agenda(s) creada(s) correctamente'
        ], 201);

    } catch (Exception $e) {
        DB::rollBack();
        
        $this->enviarNotificacionPusher('MobileUrban', 'agenda-error', [
            'error' => $e->getMessage(),
            'fecha' => now()->toDateTimeString()
        ]);

        return response()->json([
            'message' => 'Error al guardar los datos: ' . $e->getMessage(),
        ], 500);
    }
}
   

private function enviarNotificacionPusher($channel, $event, $data)
{
    try {
        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true
            ]
        );

        $pusher->trigger($channel, $event, $data);
    } catch (Exception $e) {

    }
}

    public function notificarInicioEventoEscenario($idAgenda)
    {
        DB::beginTransaction();

        try {
            $agenda = Agenda::where('id', $idAgenda)
                ->lockForUpdate()
                ->with([
                    'asignacionesResponsables' => function ($query) {
                        $query->with(['responsable', 'servicio', 'cliente']);
                    }
                ])->firstOrFail();

            if ($agenda->estado === 'EN_PROGRESO') {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'El servicio ya estaba en progreso',
                    'already_in_progress' => true
                ]);
            }

            if ($agenda->asignacionesResponsables->isEmpty()) {
                throw new Exception("No hay asignaciones de responsable para esta agenda");
            }

            $asignacion = $agenda->asignacionesResponsables->first();

            // $existingPrestacion = PrestacionServicio::where('idEscenario', $asignacion->idEscenario)
            //     ->whereDate('created_at', today())
            //     ->whereHas('detalles', function ($q) use ($asignacion) {
            //         $q->where('idServicio', $asignacion->idServicio);
            //     })
            //     ->first();
            // if ($existingPrestacion) {
            //     DB::commit();
            //     return response()->json([
            //         'success' => true,
            //         'message' => 'El servicio ya fue iniciado anteriormente',
            //         'prestacion_id' => $existingPrestacion->id,
            //         'already_exists' => true
            //     ]);
            // }

            $agenda->update(['estado' => 'EN_PROGRESO']);

           

            $prestacion = PrestacionServicio::create([
              'idEscenario' => $asignacion->idEscenario ?? null,
                'estado' => 'PENDIENTE',
                'inicioServicio' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $detalle = null;
            $shoppingCart = null;

            if ($asignacion->idServicio) {
                $detalle = DetalleServicio::create([
                    'idServicio' => $asignacion->idServicio,
                    'idPrestacionServicio' => $prestacion->id,
                    'valor' => $asignacion->servicio->valor ?? 0,
                    'observacion' => 'Servicio iniciado para cliente: ' . ($asignacion->cliente->nombre ?? ''),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                // $idCompany = KeyUtil::idCompany();
                $shoppingCart = ShoppingCart::create([
                    'idUser' => null,
                    'cantidad' => '0',
                    'estado' => 'PENDIENTE',
                    'idTercero' => $asignacion->idCliente,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'origen' => 'PUNTO POS',
                    'idCompany' => $agenda->idCompany

                ]);

                AsignacionCarritoProducto::create([
                    'idShoppingCart' => $shoppingCart->id,
                    'idProducto' => null,
                    'cantidad' => '0',
                    'valorUnitario' => $asignacion->servicio->valor ?? 0,
                    'idDetalleServicio' => $detalle->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'useTLS' => true,
                    'encrypted' => true // Add this line
                ]
            );

            $pusherData = [
                'idAgenda' => $agenda->id,
                'estado' => 'EN_PROGRESO',
                'titulo' => 'Servicio iniciado',
                'mensaje' => 'El servicio ha sido iniciado correctamente',
                'fecha' => now()->toDateTimeString(),
                'prestacion' => $prestacion,
                'detalle' => $detalle,
                'shoppingCart' => $shoppingCart,
                'is_new' => true
            ];

            $pusher->trigger(
                'MobileUrban',
                'servicio-iniciado',
                $pusherData
            );

            return response()->json([
                'success' => true,
                'agenda' => $agenda,
                'prestacion' => $prestacion,
                'detalle' => $detalle,
                'shoppingCart' => $shoppingCart,
                'message' => 'Servicio iniciado y registrado correctamente',
                'pusher_sent' => true,
                'is_new' => true
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'useTLS' => true,
                    'encrypted' => true 
                ]
            );

            $pusher->trigger(
                'agenda-channel',
                'servicio-error',
                [
                    'idAgenda' => $idAgenda,
                    'error' => $e->getMessage()
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar el servicioa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Agenda  $agenda
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Agenda $agenda)
    {
        return response()->json($agenda);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Agenda  $agenda
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Agenda $agenda)
    {
        $request->validate([
            'fechaInicial' => 'required|date',
            'horaInicial' => 'required|date_format:H:i',
        ]);

        $agenda->update([
            'fechaInicial' => $request->fechaInicial,
            'horaInicial' => $request->horaInicial,
        ]);

        return response()->json($agenda);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Agenda  $agenda
     * @return \Illuminate\Http\JsonResponse
     */
   public function destroy(Agenda $agenda)
    {
        
        DB::beginTransaction();

        try {
            
                // Cargar datos necesarios para la notificación antes de eliminar
            $asignacion = $agenda->asignacionesResponsables()->first();
            $cliente = $asignacion ? Tercero::find($asignacion->idCliente) : null;
            $servicio = $asignacion ? Servicio::find($asignacion->idServicio) : null;
            $escenario = $asignacion ? Escenario::find($asignacion->idEscenario) : null;
            $configuracion = $agenda->idConfiguracionRepeat ? ConfiguracionRepeticion::find($agenda->idConfiguracionRepeat) : null;


            $agenda->asignacionesResponsables()->delete(); 
            
            
            $agenda->delete();

        //  NUEVA NOTIFICACIÓN POR CORREO: ELIMINACIÓN ÚNICA
             if ($cliente && $servicio) {
                try {
                    $this->sendReservaEscenarioNotification(
                        $cliente, 
                        $servicio, 
                        $escenario, 
                        $agenda, 
                        'ELIMINADA', 
                        $configuracion
                    );
                } catch (Exception $e) {
                    \Log::error('Error enviando correo de ELIMINACIÓN ÚNICA de reserva: ' . $e->getMessage());
                }
            }
            
            DB::commit();
            return response()->json(null, 204);
            
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar la agenda: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function terminarServicio($idAgenda)
    {
        $agenda = Agenda::find($idAgenda);
        $agenda->update([
            'estado' => 'COMPLETADO',
            'fechaCompletado' => Carbon::now(), 
        ]);
        return response()->json($agenda);
    }


    public function updateAgendaEscenario(Request $request, $idAgenda)
    {
        $request->validate([
            'fechaInicial' => 'required|date_format:Y-m-d', // YM agregue para modificar la fecha de reserva
            'fechaFinal' => 'required|date_format:Y-m-d|after_or_equal:fechaInicial', 
            'horaInicial' => 'required|date_format:H:i',
            'comentario' => 'nullable|string',
            'horaFinal' => 'required|date_format:H:i',
            'idServicio' => 'required|integer|exists:servicios,id',
            'idTercero' => 'required|integer|exists:tercero,id',
            'idEscenario' => 'required|integer|exists:escenario,id',
        ]);
        
        DB::beginTransaction();
        
        try {
            $fechaInicial = Carbon::parse($request->fechaInicial)->format('Y-m-d');
            $fechaFinal = Carbon::parse($request->fechaFinal)->format('Y-m-d');
            $agenda = Agenda::findOrFail($idAgenda);
            
            // Cargar información previa antes de la actualización
            $asignacion = AsignacionResponsableServicio::where('idAgenda', $idAgenda)->first();
            
            // Actualizar la Agenda
            $agenda->fechaInicial = $fechaInicial; 
            $agenda->fechaFinal = $fechaFinal;
            $agenda->horaInicial = $request->horaInicial;
            $agenda->nota = $request->comentario;
            $agenda->save();
        
            // Actualizar Asignación
            if ($asignacion) {
                $asignacion->idServicio = $request->idServicio;
                $asignacion->idCliente = $request->idTercero;
                $asignacion->idEscenario = $request->idEscenario;
                $asignacion->save();
            }
            
            DB::commit();

            // AJUSTE WHATSAPP: ENVIAR MODIFICACIÓN 
        $cliente = Tercero::find($request->idTercero);
        $servicio = Servicio::find($request->idServicio);
        $escenario = Escenario::find($request->idEscenario);
        
        if ($cliente && $servicio) {
            $nombreCliente = $cliente->nombre ?? 'Cliente';
            $nombreEscenarioServicio = $escenario->nombre . ' - ' . $servicio->nombre;
            
            $fechaHoraAgenda = Carbon::parse($agenda->fechaInicial)->format('d/m/Y') 
                             . ' a las ' 
                             . Carbon::parse($agenda->horaInicial)->format('h:i a');

            $numeroSinPrefijo = $cliente->telefono;
            if (strlen($numeroSinPrefijo) == 10) { 
                $telefonoCliente = '57' . $numeroSinPrefijo;
            } else {
                $telefonoCliente = $numeroSinPrefijo; 
            }
            
            if ($telefonoCliente) {
                try {
                   $whatsappRequest = Request::create('/api/enviar-whatsapp', 'POST', [
                        'telefono' => $telefonoCliente,
                        'template_name' => 'reserva_confirmada_nexi', 
                        'language_code' => 'es_CO', 
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $nombreCliente],              // {{1}}
                                    ['type' => 'text', 'text' => $nombreEscenarioServicio],    // {{2}}
                                    ['type' => 'text', 'text' => $fechaHoraAgenda],            // {{3}}
                                ]
                            ]
                        ]
                    ]);
                    
                    $whatsappController = new WhatsAppController();
                    $whatsappResult = $whatsappController->enviarMensaje($whatsappRequest);

                    if (!($whatsappResult['success'] ?? false)) {
                        \Log::warning('FALLO WHATSAPP (Modificación Escenario): ' . ($whatsappResult['message'] ?? 'Error desconocido'));
                    }

                } catch (Exception $e) {
                    \Log::error('Error crítico al procesar el WhatsAppController (Modificación Escenario): ' . $e->getMessage());
                }
            }
        }

            // NUEVA NOTIFICACIÓN POR CORREO: MODIFICACIÓN
            $cliente = Tercero::find($request->idTercero);
            $servicio = Servicio::find($request->idServicio);
            $escenario = Escenario::find($request->idEscenario);
            $configuracion = $agenda->idConfiguracionRepeat ? ConfiguracionRepeticion::find($agenda->idConfiguracionRepeat) : null;

            if ($cliente && $servicio) {
                try {
                     $this->sendReservaEscenarioNotification(
                        $cliente, 
                        $servicio, 
                        $escenario, 
                        $agenda, 
                        'MODIFICADA', 
                        $configuracion
                    );
                } catch (Exception $e) {
                    \Log::error('Error enviando correo de MODIFICACIÓN de reserva: ' . $e->getMessage());
                }
            }
        
            return response()->json(['mensaje' => 'Agenda y asignación actualizadas correctamente']);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la agenda: ' . $e->getMessage(),
            ], 500);
        }
    }

   public function finalizarSerieReservas($idAgenda)
    {
        DB::beginTransaction();

        try {
            $agendaMaestra = Agenda::findOrFail($idAgenda);

            $idConfiguracion = $agendaMaestra->idConfiguracionRepeat;

            if (!$idConfiguracion) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Esta agenda no es parte de una serie recurrente para ser finalizada en lote.',
                ], 400);
            }
            
            $asignacionMaestra = AsignacionResponsableServicio::where('idAgenda', $agendaMaestra->id)->first();
            
            $cliente = $asignacionMaestra ? Tercero::find($asignacionMaestra->idCliente) : null;
            $servicio = $asignacionMaestra ? Servicio::find($asignacionMaestra->idServicio) : null;
            $escenario = $asignacionMaestra ? Escenario::find($asignacionMaestra->idEscenario) : null;
            $configuracion = ConfiguracionRepeticion::find($idConfiguracion);


            $count = Agenda::where('idConfiguracionRepeat', $idConfiguracion)
                ->where('estado', '!=', 'COMPLETADO') 
                ->where('estado', '!=', 'CANCELADO')
                ->update([
                    'estado' => 'COMPLETADO',
                    'fechaCompletado' => Carbon::now(),
                ]);
            
            DB::commit();

            // NUEVA NOTIFICACIÓN POR CORREO: FINALIZACIÓN DE SERIE
             if ($count > 0 && $cliente && $servicio) {
                try {
                    $this->sendReservaEscenarioNotification(
                        $cliente, 
                        $servicio, 
                        $escenario, 
                        $agendaMaestra, 
                        'FINALIZADA_SERIE', 
                        $configuracion
                    );
                } catch (Exception $e) {
                    \Log::error('Error enviando correo de FINALIZACIÓN DE SERIE: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Se han marcado $count reservas de la serie como COMPLETADO.",
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al finalizar la serie de reservas: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    
   
    public function destroySerie($idConfiguracion)
{
    DB::beginTransaction();

    try {
        // 1. Obtener la agenda maestra y los datos de notificación ANTES de eliminar
        $configuracion = ConfiguracionRepeticion::findOrFail($idConfiguracion);
        $agendaMaestra = Agenda::find($configuracion->id_agenda_maestra);
        
        $cliente = null;
        $servicio = null;
        $escenario = null;

        if ($agendaMaestra) {
            $asignacionMaestra = AsignacionResponsableServicio::where('idAgenda', $agendaMaestra->id)->first();
            $cliente = $asignacionMaestra ? Tercero::find($asignacionMaestra->idCliente) : null;
            $servicio = $asignacionMaestra ? Servicio::find($asignacionMaestra->idServicio) : null;
            $escenario = $asignacionMaestra ? Escenario::find($asignacionMaestra->idEscenario) : null;
        }

        $agendaIds = Agenda::where('idConfiguracionRepeat', $idConfiguracion)
                            ->pluck('id');

        if ($agendaIds->isEmpty()) {
            ConfiguracionRepeticion::destroy($idConfiguracion);
            DB::commit();
            return response()->json(['message' => 'Configuración de repetición eliminada. No se encontraron reservas asociadas.'], 200);
        }

        // 2. Ejecutar la eliminación
        AsignacionResponsableServicio::whereIn('idAgenda', $agendaIds)->delete();
        Agenda::whereIn('id', $agendaIds)->delete();
        ConfiguracionRepeticion::destroy($idConfiguracion);

        DB::commit();

        //  NUEVA NOTIFICACIÓN POR CORREO: ELIMINACIÓN DE SERIE
         if ($cliente && $servicio && $agendaMaestra) {
            try {
                 $this->sendReservaEscenarioNotification(
                    $cliente, 
                    $servicio, 
                    $escenario, 
                    $agendaMaestra, 
                    'ELIMINADA_SERIE', 
                    $configuracion
                );
            } catch (Exception $e) {
                \Log::error('Error enviando correo de ELIMINACIÓN DE SERIE: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Serie de reservas y sus dependencias eliminadas correctamente.'], 200);

    } catch (Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al eliminar la serie de reservas: ' . $e->getMessage(),
        ], 500);
    }
}


/**
 * Crea la agenda maestra y, si es recurrente, las agendas repetidas.
 * @param array $data Todos los datos del request
 * @param int $idCompany
 * @param int $idUser
 * @return \App\Models\Agenda El objeto de la primera agenda creada (maestra).
 */
private function createAgendas(array $data, int $idCompany, int $idUser)
{
    $recurrenciaTipo = $data['recurrenciaTipo'];
    $fechaFinRepeticion = $data['fechaFinRepeticion'];
    
    $fechaActual = Carbon::parse($data['date']); 
    $fechaLimite = $fechaFinRepeticion ? Carbon::parse($fechaFinRepeticion) : $fechaActual;
    
    $idConfiguracion = null;
    $primeraAgenda = null;

    // 1. CREAR CONFIGURACIÓN DE REPETICIÓN (Solo si es recurrente)
    if ($recurrenciaTipo !== 'NO_REPETIR') {
        $configuracion = ConfiguracionRepeticion::create([
            'tipo_recurrencia' => $recurrenciaTipo,
            'fecha_fin_repeticion' => $fechaLimite->format('Y-m-d'),
            'idCompany' => $idCompany,
        ]);
        $idConfiguracion = $configuracion->id;
    }


    $fechaReservaInicio = Carbon::parse($data['date']);
    $fechaReservaFin = Carbon::parse($data['fechaFinalReserva'] ?? $data['date']); 
    
    $diffDays = $fechaReservaInicio->diffInDays($fechaReservaFin);

   while ($fechaActual->lessThanOrEqualTo($fechaLimite)) {
            
            $horaInicio = $data['time'];
            $horaFin = $data['horaFinal'];
            
            $currentFechaInicial = $fechaActual->format('Y-m-d');
            $currentFechaFinal = $fechaActual->copy()->addDays($diffDays)->format('Y-m-d');

            $isAvailable = !Agenda::where('idCompany', $idCompany) // Filtro de seguridad (faltaba)
                ->where('tipo', 'ESCENARIO') 
                ->whereNotIn('estado', ['COMPLETADO', 'CANCELADO'])
                
                ->whereHas('asignacionesResponsables', function ($query) use ($data) {
                    $query->where('idEscenario', $data['idEscenario']);
                })
                
                ->where(function ($query) use ($currentFechaInicial, $currentFechaFinal, $horaInicio, $horaFin) {
                    
                    $query->where(DB::raw("CONCAT(fechaInicial, ' ', horaInicial)"), '<', "{$currentFechaFinal} {$horaFin}");

                    $query->where(DB::raw("CONCAT(fechaFinal, ' ', horaFinal)"), '>', "{$currentFechaInicial} {$horaInicio}");
                })
                ->exists();

        if ($isAvailable) { 
            
            // Creación del registro en la tabla 'agenda'
            $agenda = Agenda::create([
                'fechaInicial' => $currentFechaInicial,
                'fechaFinal' => $currentFechaFinal, 
                'horaInicial' => $data['time'],
                'horaFinal' => $data['horaFinal'], 
                'idUser' => $idUser, 
                'estado' => 'AGENDADO',
                'idCompany' => $idCompany,
                'tipo' => 'ESCENARIO',
                'nota' => $data['comentario'] ?? null,
                'idConfiguracionRepeat' => $idConfiguracion, 
            ]);
    
            AsignacionResponsableServicio::create([
                'idServicio' => $data['idServicio'],
                'idCliente' => $data['idTercero'],
                'idAgenda' => $agenda->id,
                'idEscenario' => $data['idEscenario'],
            ]);

            if (!$primeraAgenda) {
                $primeraAgenda = $agenda;
                
                if ($idConfiguracion) {
                    ConfiguracionRepeticion::where('id', $idConfiguracion)
                        ->update(['id_agenda_maestra' => $primeraAgenda->id]);
                }
            }
        } else if ($recurrenciaTipo !== 'NO_REPETIR') {
        }
        
        if ($recurrenciaTipo === 'NO_REPETIR') {
            break; 
        }
        
        // Lógica de avance de fecha usando Carbon
        switch ($recurrenciaTipo) {
            case 'DIARIO':
                $fechaActual->addDay();
                break;
            case 'SEMANAL':
                $fechaActual->addWeek();
                break;
            case 'QUINCENAL':
                $fechaActual->addDays(14);
                break;
            case 'MENSUAL':
                $fechaActual->addMonth(); 
                break;
        }
    }
    
    if (!$primeraAgenda) {
         throw new Exception("No se pudo crear ninguna reserva. El escenario está ocupado en el rango de fechas solicitado.");
    }
    
    return $primeraAgenda;
}


private function sendReservaEscenarioNotification(
    Tercero $cliente, 
    Servicio $servicio, 
    ?Escenario $escenario, 
    Agenda $agenda, 
    string $action, 
    ?ConfiguracionRepeticion $configuracion
): void
{
    if (!$cliente->email) {
        \Log::warning("No se pudo enviar la notificación. El cliente {$cliente->id} no tiene correo electrónico.");
        return;
    }

    $nombreCliente = $cliente->nombre ?? $cliente->nombre1;
    $escenarioNombre = optional($escenario)->nombre ?? 'N/A';
    
    // Calcula la fecha y hora de inicio
    $fechaHoraInicio = Carbon::parse("{$agenda->fechaInicial} {$agenda->horaInicial}")->format('d/m/Y H:i');

    // 🚀 SOLUCIÓN 2: Calcula la fecha y hora de finalización completa
    // Usa fechaFinal, si existe, para la parte de la fecha.
    $fechaHoraFinCompleta = $agenda->fechaFinal ? 
        Carbon::parse("{$agenda->fechaFinal} {$agenda->horaFinal}")->format('d/m/Y H:i') : 
        Carbon::parse("{$agenda->fechaInicial} {$agenda->horaFinal}")->format('d/m/Y H:i'); 

    // Define el string de duración para el correo
    $duracionCompleta = $fechaHoraInicio . ' - ' . $fechaHoraFinCompleta;


    $esSerie = $configuracion !== null;
    $tipoRecurrencia = $esSerie ? $configuracion->tipo_recurrencia : 'única';

    switch ($action) {
        case 'CREADA':
            $subject = $esSerie ? "Confirmación de Serie de Reservas: {$servicio->nombre}" : "Confirmación de Reserva: {$servicio->nombre}";
            $intro = "Tu reserva para el **{$servicio->nombre}** en el escenario **{$escenarioNombre}** ha sido **CONFIRMADA** por nuestro administrador.";
            $details = [
                "Duración" => $duracionCompleta, // <-- Usar duración completa (incluyendo fecha de fin)
                "Tipo de Reserva" => $esSerie ? "Serie Recurrente ({$tipoRecurrencia})" : "Única",
                "Estado Actual" => $agenda->estado,
                "Comentarios" => $agenda->nota ?? 'Sin notas.',
            ];
            break;

        case 'MODIFICADA':
            $subject = "Modificación de tu Reserva: {$servicio->nombre}";
            $intro = "Tu reserva para el **{$servicio->nombre}** ha sido **MODIFICADA** por nuestro administrador. Por favor, revisa los nuevos detalles:";
            $details = [
                "Nueva Duración" => $duracionCompleta, // <-- Usar duración completa (incluyendo fecha de fin)
                "Escenario" => $escenarioNombre,
                "Comentarios" => $agenda->nota ?? 'Sin notas.',
            ];
            break;

        case 'ELIMINADA':
            $subject = "Cancelación de tu Reserva Única: {$servicio->nombre}";
            $intro = "Tu reserva única para el **{$servicio->nombre}** en la fecha **{$fechaHoraInicio}** ha sido **ELIMINADA** por el administrador.";
            $details = [
                "Motivo" => 'Cancelación por el administrador.',
                "Escenario" => $escenarioNombre,
            ];
            break;

        case 'ELIMINADA_SERIE':
            $subject = "Cancelación de la Serie de Reservas: {$servicio->nombre}";
            $intro = "Toda la serie de reservas ({$tipoRecurrencia}) para el servicio **{$servicio->nombre}** ha sido **ELIMINADA** por el administrador. Esto incluye todas las reservas hasta el {$configuracion->fecha_fin_repeticion}.";
            $details = [
                "Reserva Maestra" => $fechaHoraInicio,
                "Tipo de Recurrencia" => $tipoRecurrencia,
            ];
            break;

        case 'FINALIZADA_SERIE':
            $subject = "Serie de Reservas Finalizada: {$servicio->nombre}";
            $intro = "La serie de reservas ({$tipoRecurrencia}) para el servicio **{$servicio->nombre}** ha sido marcada como **COMPLETADA** por el administrador. ¡Esperamos que hayas disfrutado de tu tiempo!";
            $details = [
                "Reserva Maestra" => $fechaHoraInicio,
                "Estado Final" => $agenda->estado,
            ];
            break;

        default:
            return;
    }

    $message = "¡Hola **{$nombreCliente}**!\n\n"
        . "{$intro}\n\n";

    $message .= "**Detalles de la Reserva**:\n";
    foreach ($details as $key => $value) {
        $message .= "- **{$key}**: {$value}\n";
    }

    $message .= "\n\n"
        . "Si tienes alguna pregunta, por favor, contacta a la administración.\n"
        . "Gracias por elegirnos.";

if ($cliente->email) {
    try {
        \Mail::to($cliente->email)->send(new \App\Mail\MailGeneral($subject, $message));
    } catch (Exception $e) {
        \Log::error('Error enviando correo de RESERVA de escenario: ' . $e->getMessage());
    }
}
}


public function checkDisponibilidad(Request $request)
{
    $request->validate([
        'escenario_id' => 'required|integer|exists:escenario,id',
        'fecha_inicio' => 'required|date_format:Y-m-d\TH:i:s', 
        'fecha_fin' => 'required|date_format:Y-m-d\TH:i:s', 
        'exclude_id' => 'nullable|integer', 
    ]);

    $idEscenario = $request->escenario_id;
    $fechaInicioStr = $request->fecha_inicio;
    $fechaFinStr = $request->fecha_fin;
    $idAExcluir = $request->exclude_id;
    
    $fecha = Carbon::parse($fechaInicioStr)->format('Y-m-d');
    $horaInicio = Carbon::parse($fechaInicioStr)->format('H:i:s');
    $horaFin = Carbon::parse($fechaFinStr)->format('H:i:s');
    
    $conflictingAgenda = Agenda::where('fechaInicial', $fecha)
        ->whereNotIn('estado', ['COMPLETADO', 'CANCELADO'])
        
        ->where(function ($query) use ($horaInicio, $horaFin) {
            $query->where('horaInicial', '<', $horaFin)
                  ->where('horaFinal', '>', $horaInicio);
        })

        ->whereHas('asignacionesResponsables', function ($query) use ($idEscenario) {
            $query->where('idEscenario', $idEscenario);
        })
        
        ->when($idAExcluir, function ($query, $id) {
            return $query->where('id', '!=', $id);
        })
        ->first();

    if ($conflictingAgenda) {
        return response()->json([
            'available' => false,
            'message' => 'El escenario ya está reservado en ese lapso de tiempo.',
            'conflict_id' => $conflictingAgenda->id,
        ], 409); 
    }

    return response()->json([
        'available' => true,
        'message' => 'El escenario está disponible.',
    ], 200);
} // CIERRE DEL MÉTODO


}
