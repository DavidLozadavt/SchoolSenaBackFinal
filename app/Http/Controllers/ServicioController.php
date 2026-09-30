<?php

namespace App\Http\Controllers;

use App\Events\NuevaNotificacionAdmin;
use App\Models\Notificacion; 
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use App\Models\ActivationCompanyUser;
use App\Models\Agenda;
use App\Models\AsignacionCarritoProducto;
use App\Models\AsignacionEscenarioServicio;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\AsignacionResponsableServicio;
use App\Models\ClaseServicio;
use App\Models\DetalleFactura;
use App\Models\DetalleServicio;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\PrestacionServicio;
use App\Models\ResponsableServicio;
use App\Models\Servicio;
use App\Models\ShoppingCart;
use App\Models\Tercero;
use App\Models\TipoFactura;
use App\Models\TipoServicio;
use App\Models\Transaccion;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Bancolombia\Wompi;
use Exception;
use App\Jobs\GenerarCorreoGeneral;
use App\Models\User;
use App\Http\Controllers\WhatsAppController; 


class ServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $idCompany =  KeyUtil::idCompany();

        $claseServicios = Servicio::where('idCompany', $idCompany)

            ->with('tipoServicio.claseServicio', 'escenarios', 'responsables.persona')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($claseServicios);
    }

    public function getServicesByCompany()
    {
        $idCompany = KeyUtil::idCompany();
        $services = Servicio::where('idCompany', $idCompany)->get();
        return response()->json($services);
    }

    
    public function store(Request $request)
    {
        $data = $request->except(['responsables']); 
        $servicio = new Servicio($data);
        $servicio->idCompany = KeyUtil::idCompany();
        
        $servicio->saveImageServicio($request);
        $servicio->save(); 

        $responsablesIds = $request->input('responsables'); 

        $pivotData = [];
        if (!empty($responsablesIds) && is_array($responsablesIds)) {
            foreach ($responsablesIds as $responsableId) {

                $pivotData[$responsableId] = ['estado' => 'ACTIVO']; 
            }
        }
            
        $servicio->responsables()->sync($pivotData); 

    $servicio->load('responsables');

        return response()->json($servicio, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function show(int $id)
    {
        $Servicio = Servicio::find($id);

        return response()->json($Servicio);
    }

   
     public function update(Request $request, int $id)
    {
        $data = $request->except(['responsables']); 
        $servicio = Servicio::findOrFail($id);
        
        $servicio->fill($data);
        $servicio->saveImageServicio($request);
        $servicio->save();

        $responsablesIds = $request->input('responsables'); 

        $pivotData = [];
        if (!empty($responsablesIds) && is_array($responsablesIds)) {
            foreach ($responsablesIds as $responsableId) {
                $pivotData[$responsableId] = ['estado' => 'ACTIVO']; 
            }
        }
            
        $servicio->responsables()->sync($pivotData); 

    $servicio->load('responsables');

        return response()->json($servicio);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $Servicio = Servicio::findOrFail($id);
        $Servicio->delete();

        return response()->json([], 204);
    }


    public function getServicesByCompanyWebPage($id)
    {

        $services = Servicio::where('idCompany', $id)
            ->with('tipoServicio', 'categoriaServicio')->get();
        return response()->json($services);
    }

    public function asignacionEscenarioServicio(Request $request)
    {
        // Tomar correctamente lo que viene del frontend
        $idsEscenarios = $request->input('escenarios_id', []);
        $idServicio = $request->input('servicio_id');

        // Validación rápida
        if (!$idServicio) {
            return response()->json(['error' => 'servicio_id es requerido'], 422);
        }

        // Eliminar asignaciones anteriores
        AsignacionEscenarioServicio::where('idServicio', $idServicio)->delete();

        // Crear nuevas asignaciones
        $asignaciones = [];
        foreach ($idsEscenarios as $idEscenario) {
            $asignacion = AsignacionEscenarioServicio::create([
                'idServicio' => $idServicio,
                'idEscenario' => $idEscenario
            ]);

            $asignaciones[] = $asignacion;
        }

        return response()->json($asignaciones, 201);
    }


    public function getEscenariosByServicio(Request $request, $id)
    {
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $cantidadHuespedes = $request->input('huespedes');

        $query = AsignacionEscenarioServicio::where('idServicio', $id);


        if ($fechaInicio && $fechaFin) {
            $ocupados = AsignacionResponsableServicio::whereHas('agenda', function ($query) use ($fechaInicio, $fechaFin) {
                $query->where(function ($q) use ($fechaInicio, $fechaFin) {
                    $q->where('fechaInicial', '<=', $fechaFin)
                        ->where('fechaFinal', '>=', $fechaInicio);
                });
            })->pluck('idEscenario')->toArray();

            $query->whereNotIn('idEscenario', $ocupados);
        }

        $escenarios = $query
            ->with(['escenario.imagenes'])
            ->get()
            ->filter(function ($asignacion) use ($cantidadHuespedes) {
                if ($cantidadHuespedes) {
                    return $asignacion->escenario->capacidad >= $cantidadHuespedes;
                }
                return true;
            })
            ->values();

        return response()->json($escenarios);
    }




    public function ServiciosByEscenario($idEscenario)
    {
        $servicios = AsignacionEscenarioServicio::with('servicio')
            ->where('idEscenario', $idEscenario)
            ->get()
            ->pluck('servicio');
        return response()->json($servicios);
    }



    public function disponibilidadPrestador($id)
    {
        $ids = is_array($id) ? $id : [$id];

        $fechasOcupadas = AsignacionResponsableServicio::with(['agenda', 'cliente.shoppingCarts.asignaciones'])
            ->whereIn('idResponsable', $ids)
            ->whereHas('agenda', function ($q) {
                $q->where('estado', 'AGENDADO')
                    ->where('tipo', 'SERVICIO');
            })
            ->whereHas('cliente.shoppingCarts', function ($q) {
                $q->where('estado', 'PENDIENTE');
            })
            ->get();

        return response()->json($fechasOcupadas);
    }

public function storeAgendaServicioNexiService(Request $request, $id)
    {
        DB::beginTransaction();
        $whatsappStatus = 'No intentada'; // Variable para guardar el estado de la notificación

        try {
            // 🔹 1. Buscar servicio, responsable y cliente
            $servicio = Servicio::findOrFail($request->input('idServicio'));
            $responsable = ResponsableServicio::findOrFail($request->input('idResponsable'));
            $cliente = Tercero::where('email', $request->input('emailCliente'))->firstOrFail();

            // 🔹 2. Crear agenda
            $agenda = new Agenda();
            $agenda->estado = 'AGENDADO';
            $agenda->fechaInicial = $request->input('fechaInicio');
            $agenda->horaInicial = $request->input('horaInicial');
            $agenda->horaFinal = $request->input('horaFinal');
            $agenda->descripcion = $request->input('descripcion');
            $agenda->nota = $request->input('nota');
            $agenda->idCompany = $id;
            $agenda->tipo = 'SERVICIO';
            $agenda->save();

            // 🔹 3. Asignar responsable y cliente
            $asignacion = new AsignacionResponsableServicio();
            $asignacion->idServicio = $servicio->id;
            $asignacion->idCliente = $cliente->id;
            $asignacion->idAgenda = $agenda->id;
            $asignacion->idResponsable = $responsable->idPersona;
            $asignacion->save();

            // 🔹 4. Crear prestación de servicio
            $prestacionServicio = new PrestacionServicio();
            $prestacionServicio->estado = 'PENDIENTE';
            $prestacionServicio->diagnostico = $agenda->nota;
            $fechaHoraInicio = $agenda->fechaInicial . ' ' . $agenda->horaInicial;
            $prestacionServicio->inicioServicio = date('Y-m-d H:i:s', strtotime($fechaHoraInicio));
            $prestacionServicio->idResponsable = $responsable->id;
            $prestacionServicio->save();

            // 🔹 5. Crear detalle del servicio
            $detalleServicio = new DetalleServicio();
            $detalleServicio->idServicio = $servicio->id;
            $detalleServicio->valor = $servicio->valor;
            $detalleServicio->idPrestacionServicio = $prestacionServicio->id;
            $detalleServicio->save();

            $asignacion->idDetalleServicio = $detalleServicio->id;
            $asignacion->save();

            // 🔹 6. Carrito de compras
            $shoppingCart = ShoppingCart::where('estado', 'PENDIENTE')
                ->where('idTercero', $cliente->id)
                ->where('idCompany', $id)
                ->first();

            if (!$shoppingCart || $shoppingCart->origen === 'PUNTO POS') {
                $shoppingCart = ShoppingCart::create([
                    'estado' => 'PENDIENTE',
                    'idTercero' => $cliente->id,
                    'origen' => 'WEB',
                    'idCompany' => $id
                ]);
            }

            // 🔹 7. Asignar producto al carrito
            AsignacionCarritoProducto::create([
                'idShoppingCart' => $shoppingCart->id,
                'idProducto' => null,
                'cantidad' => 0,
                'valorUnitario' => $servicio->valor ?? 0,
                'idDetalleServicio' => $detalleServicio->id,
            ]);

            // 🔹 8. Enviar correo de confirmación
            $subject = "Confirmación de tu agenda: " . e($servicio->nombre);
            $message = "¡Hola {$cliente->nombre}!\n\n"
                . "Tu agenda para el servicio '{$servicio->nombre}' ha sido confirmada en NEXISERVICE COL.\n\n"
                . "Fecha: {$agenda->fechaInicial} {$agenda->horaInicial}\n"
                . "Estado: {$agenda->estado}\n"
                . "Precio: {$servicio->valor}\n"
                . "Responsable: " . e(optional($responsable->persona)->nombre ?? 'Sin asignar') . "\n\n"
                . "Gracias por elegirnos.";

            try {
                \Mail::to($cliente->email)->send(new \App\Mail\MailGeneral($subject, $message));
            } catch (Exception $e) {
                \Log::error('Error enviando correo: ' . $e->getMessage());
            }

            // 🔹 9. Crear notificación para administradores
            try {
                $this->crearNotificacionReservaAdmin($agenda, $servicio, $cliente, $responsable, $id);
            } catch (Exception $e) {
                \Log::error('Error creando notificación: ' . $e->getMessage());
            }

            DB::commit(); 

            // AJUSTE WHATSAPP: 
            $nombreCliente = $cliente->nombre ?? 'Cliente';
            $nombreServicio = $servicio->nombre;
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
                        ['type' => 'text', 'text' => $nombreCliente],     
                        ['type' => 'text', 'text' => $nombreServicio],    
                        ['type' => 'text', 'text' => $fechaHoraAgenda],   
        ]
                ]
            ]
                    ]);

                    
                    

                    $whatsappController = new WhatsAppController();
                    $whatsappResult = $whatsappController->enviarMensaje($whatsappRequest);

                    if ($whatsappResult['success'] ?? false) {
                        $whatsappStatus = 'Notificación enviada con éxito a WhatsApp (mensaje de prueba).';
                    } else {
                        $whatsappStatus = 'FALLO WHATSAPP: ' . ($whatsappResult['message'] ?? 'Error desconocido');
                    }

                } catch (Exception $e) {
                    \Log::error('Error crítico al procesar el WhatsAppController: ' . $e->getMessage());
                    $whatsappStatus = 'Error CRÍTICO al procesar el envío de WhatsApp: ' . $e->getMessage();
                }
            } else {
                $whatsappStatus = 'No se envió: Número de teléfono no válido o no encontrado.';
            }

            return response()->json([
                'agenda' => $agenda,
                'whatsapp_status' => $whatsappStatus, 
            ], 201);

        } catch (Exception $e) {
// ...
            DB::rollBack();
            Log::error('Error al guardar agenda: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al guardar la agenda',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteAgendaNexiService(Request $request)
    {
        $idAsignacion = $request->input('idAsignacionCarritoProducto');

        DB::beginTransaction();

        try {
            $asignacion = AsignacionCarritoProducto::find($idAsignacion);

            if (!$asignacion) {
                return response()->json(['error' => 'Asignación no encontrada'], 404);
            }

            $shoppingCartId = $asignacion->idShoppingCart;
            $idDetalle = $asignacion->idDetalleServicio;

            $asignacion->delete();

            if (!is_null($idDetalle)) {
                $asignacionResponsable = AsignacionResponsableServicio::where('idDetalleServicio', $idDetalle)->first();

                if ($asignacionResponsable) {
                    $idAgenda = $asignacionResponsable->idAgenda;
                    $asignacionResponsable->delete();

                    $agenda = Agenda::find($idAgenda);
                    if ($agenda) {
                        $agenda->delete();
                    }
                }


                $detalleServicio = DetalleServicio::find($idDetalle);

                if ($detalleServicio) {
                    $idPrestacion = $detalleServicio->idPrestacionServicio;
                    $detalleServicio->delete();

                    if (!is_null($idPrestacion)) {
                        $otrosDetalles = DetalleServicio::where('idPrestacionServicio', $idPrestacion)->count();

                        if ($otrosDetalles === 0) {
                            $prestacionServicio = PrestacionServicio::find($idPrestacion);

                            if ($prestacionServicio) {
                                $prestacionServicio->delete();
                            }
                        }
                    }
                }
            }


            $remainingAssignments = AsignacionCarritoProducto::where('idShoppingCart', $shoppingCartId)->count();

            if ($remainingAssignments === 0) {
                $shoppingCart = ShoppingCart::find($shoppingCartId);

                if ($shoppingCart) {
                    $shoppingCart->delete();
                }

                DB::commit();
                return response()->json(['message' => 'Carrito eliminado, no quedan asignaciones', 'code' => 2001], 200);
            }

            DB::commit();
            return response()->json(['message' => 'Servicio eliminado correctamente', 'code' => 200], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ocurrió un error al eliminar el servicio.',
                'exception' => $e->getMessage()
            ], 500);
        }
    }




    public function storeReservaNexiService(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // Buscar cliente
            $cliente = Tercero::where('email', $request->input('emailCliente'))->first();
            if (!$cliente) {
                throw new Exception('Cliente no encontrado.');
            }

            // Buscar servicio
            $servicio = Servicio::find($request->input('idServicio'));
            if (!$servicio) {
                throw new Exception('Servicio no encontrado.');
            }

            // Crear agenda
            $agenda = new Agenda();
            $agenda->estado = 'PENDIENTE';
            $agenda->fechaInicial = Carbon::parse($request->input('fechaInicio'))->toDateTimeString();
            $agenda->fechaFinal = Carbon::parse($request->input('fechaFinal'))->toDateTimeString();
            $agenda->nota = $request->input('nota');
            $agenda->idCompany = $id;
            $agenda->tipo = 'SERVICIO';
            $agenda->save();

            // Crear asignación responsable de servicio
            $asignacion = new AsignacionResponsableServicio();
            $asignacion->idServicio = $request->idServicio;
            $asignacion->idCliente = $cliente->id;
            $asignacion->idAgenda = $agenda->id;
            $asignacion->idEscenario = $request->input('idEscenario') ?? null; // opcional

            // Crear prestación de servicio
            $prestacionServicio = new PrestacionServicio();
            $prestacionServicio->estado = 'PENDIENTE';
            $prestacionServicio->inicioServicio = Carbon::parse($agenda->fechaInicial)->startOfDay()->toDateTimeString();
            $prestacionServicio->finServicio = Carbon::parse($agenda->fechaFinal)->endOfDay()->toDateTimeString();
            $prestacionServicio->idEscenario = $request->input('idEscenario') ?? null; // opcional
            $prestacionServicio->save();

            // Crear detalle del servicio
            $detalleServicio = new DetalleServicio();
            $detalleServicio->idServicio = $request->idServicio;
            $detalleServicio->valor = $servicio->valor;
            $detalleServicio->idPrestacionServicio = $prestacionServicio->id;
            $detalleServicio->save();

            // Vincular detalle con la asignación
            $asignacion->idDetalleServicio = $detalleServicio->id;
            $asignacion->save();

            // Manejar carrito de compras
            $shoppingCart = ShoppingCart::where('estado', 'PENDIENTE')
                ->where('idTercero', $cliente->id)
                ->where('idCompany', $id)
                ->first();

            if (!$shoppingCart || $shoppingCart->origen === 'PUNTO POS') {
                $shoppingCart = ShoppingCart::create([
                    'estado' => 'PENDIENTE',
                    'idTercero' => $cliente->id,
                    'origen' => 'WEB',
                    'idCompany' => $id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            AsignacionCarritoProducto::create([
                'idShoppingCart' => $shoppingCart->id,
                'idProducto' => null,
                'cantidad' => 0,
                'valorUnitario' => $servicio->valor ?? 0,
                'idDetalleServicio' => $detalleServicio->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Enviar correo de confirmación
            $subject = "Confirmación de tu reserva: " . e($servicio->nombre);
            $message = "¡Hola " . e($cliente->nombre) . "!\n\n" .
                "Tu reserva para el servicio '" . e($servicio->nombre) . "' ha sido confirmada en NEXISERVICE COL. A continuación, los detalles de tu reserva:\n\n" .
                "Estado de la reserva: " . e($agenda->estado) . "\n" .
                "Fecha de inicio: " . e($agenda->fechaInicial) . "\n" .
                "Fecha de finalización: " . e($agenda->fechaFinal) . "\n" .
                "Precio: " . e($servicio->valor) . "\n" .
                "Número de personas: " . (int)$request->input('numeroPersonas', 1) . "\n\n" .
                "Te agradecemos por elegir NEXISERVICE COL y esperamos que disfrutes de tu experiencia.\n\n" .
                "¡Nos vemos pronto!\n" .
                "El equipo de NEXISERVICE COL.";

            try {
                \Mail::to($cliente->email)->send(
                    new \App\Mail\MailGeneral($subject, $message)
                );
            } catch (Exception $e) {
                \Log::error('Error enviando correo: ' . $e->getMessage());
                \Log::error($e->getTraceAsString());

                return response()->json([
                    'error' => 'No se pudo enviar el correo',
                    'mensaje' => $e->getMessage()
                ], 500);
            }

            DB::commit();

            return response()->json($agenda, 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al guardar la reserva',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }




    public function cancelReservaNexiService(Request $request)
    {

        $idShoppingCart = $request->input('idShoppingCart');

        $shoppingCart = ShoppingCart::with(['asignaciones.detalleServicio.prestacionServicio'])->find($idShoppingCart);

        if (!$shoppingCart) {
            return response()->json(['error' => 'Carrito no encontrado'], 404);
        }

        $shoppingCart->estado = 'CANCELADO';
        $shoppingCart->save();

        foreach ($shoppingCart->asignaciones as $asignacion) {
            $detalle = $asignacion->detalleServicio;


            if ($detalle && $detalle->prestacionServicio) {
                $detalle->prestacionServicio->estado = 'CANCELADO';
                $detalle->prestacionServicio->save();
            }


            $asignacionesResponsables = AsignacionResponsableServicio::where('idDetalleServicio', $detalle->id)->get();

            foreach ($asignacionesResponsables as $asignacionResponsable) {
                if ($asignacionResponsable->agenda) {
                    $asignacionResponsable->agenda->estado = 'CANCELADO';
                    $asignacionResponsable->agenda->save();
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Reserva cancelada correctamente']);
    }

 


public function updateAgendaServicioNexiService(Request $request, int $idAgenda): JsonResponse
{
    DB::beginTransaction();

    try {
        // ... (Lógica de validación, búsqueda de Agenda, Asignación, Prestador)
        $request->validate([
            'fechaInicio' => 'required|date',
            'horaInicial' => 'required|date_format:H:i',
            'idServicio' => 'required|integer',
            'idResponsable' => 'required|integer', 
            'idCliente' => 'required|integer',     
        ]);

        $agenda = Agenda::find($idAgenda);
        if (!$agenda) {
            throw new Exception("Agenda ID {$idAgenda} no encontrada.");
        }
        $asignacion = AsignacionResponsableServicio::where('idAgenda', $agenda->id)->first();
        if (!$asignacion) {
            throw new Exception("Asignación de responsable no encontrada para la Agenda ID {$idAgenda}.");
        }
        
        $responsableServicio = ResponsableServicio::find($request->input('idResponsable'));
        if (!$responsableServicio) {
            throw new Exception('Prestador de servicio no encontrado con ID: ' . $request->input('idResponsable'));
        }

        //  Actualizar la tabla AGENDA
        $agenda->update([
            'fechaInicial' => $request->input('fechaInicio'),
            'horaInicial' => $request->input('horaInicial'),
            'nota' => $request->input('nota') ?? $agenda->nota,
        ]);
        
        // Actualizar la tabla ASIGNACION_RESPONSABLE_SERVICIO
        $asignacion->update([
            'idServicio' => $request->input('idServicio'),
            'idCliente' => $request->input('idCliente'),
            'idResponsable' => $responsableServicio->idPersona, // Usamos el ID de Persona asociado
        ]);
        
        //Actualizar PrestacionServicio y DetalleServicio
        $detalleServicio = DetalleServicio::find($asignacion->idDetalleServicio);
        if ($detalleServicio) {
            $prestacion = PrestacionServicio::find($detalleServicio->idPrestacionServicio);
            $servicio = Servicio::find($request->input('idServicio')); 
            if ($prestacion) {
                $fechaHoraInicio = $agenda->fechaInicial . ' ' . $agenda->horaInicial;
                $prestacion->update([
                    'inicioServicio' => date('Y-m-d H:i:s', strtotime($fechaHoraInicio)),
                    'idResponsable' => $responsableServicio->id, 
                    'diagnostico' => $agenda->nota,
                ]);
            }

            if ($servicio) {
                 $detalleServicio->update([
                    'idServicio' => $request->input('idServicio'),
                    'valor' => $servicio->valor,
                ]);
            }
        }
        
        
        DB::commit();

        // AJUSTE WHATSAPP: NOTIFICACIÓN DE MODIFICACIÓN 
        try {
            $cliente = Tercero::find($request->input('idCliente'));
            $servicio = $servicio ?? Servicio::find($request->input('idServicio')); 
            $responsableServicio = $responsableServicio ?? ResponsableServicio::find($request->input('idResponsable'));

            if ($cliente && $servicio && $cliente->telefono) {
                
                $nombreCliente = $cliente->nombre ?? $cliente->nombre1;
                $nombreServicio = $servicio->nombre; 
                
                $fechaHoraAgenda = Carbon::parse($agenda->fechaInicial)->format('d/m/Y') 
                                 . ' a las ' 
                                 . Carbon::parse($agenda->horaInicial)->format('h:i a');

                $numeroSinPrefijo = $cliente->telefono;
                if (strlen($numeroSinPrefijo) == 10) { 
                    $telefonoCliente = '57' . $numeroSinPrefijo;
                } else {
                    $telefonoCliente = $numeroSinPrefijo; 
                }

                $whatsappRequest = Request::create('/api/enviar-whatsapp', 'POST', [
                    'telefono' => $telefonoCliente,
                    'template_name' => 'reserva_confirmada_nexi', 
                    'language_code' => 'es_CO', 
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $nombreCliente],     
                                ['type' => 'text', 'text' => $nombreServicio],    
                                ['type' => 'text', 'text' => $fechaHoraAgenda],   
                            ]
                        ]
                    ]
                ]);
                
                $whatsappController = new WhatsAppController();
                $whatsappResult = $whatsappController->enviarMensaje($whatsappRequest);

                if (!($whatsappResult['success'] ?? false)) {
                    \Log::warning('FALLO WHATSAPP (Modificación Servicio): ' . ($whatsappResult['message'] ?? 'Error desconocido'));
                }

            }
        } catch (Exception $e) {
            \Log::error('Error crítico al procesar el WhatsAppController (Modificación Servicio): ' . $e->getMessage());
        }

        // Notificación al cliente por Modificación (USANDO ENVÍO SÍNCRONO)
        try {
            $cliente = Tercero::find($request->input('idCliente'));
            $servicio = $servicio ?? Servicio::find($request->input('idServicio')); 
            
            if ($cliente && $servicio && $cliente->email) {
                $subject = "Modificación de tu Reserva: " . e($servicio->nombre);
                $message = "¡Hola " . e($cliente->nombre ?? $cliente->nombre1) . "!\n\n"
                    . "Tu reserva para el servicio '" . e($servicio->nombre) . "' ha sido **MODIFICADA** por el administrador.\n\n"
                    . "Nuevos detalles:\n"
                    . "Fecha y Hora: " . e($agenda->fechaInicial) . " " . e($agenda->horaInicial) . "\n"
                    . "Prestador: " . e(optional($responsableServicio->persona)->nombre ?? 'Sin asignar') . "\n"
                    . "Motivo/Nota: " . e($agenda->nota) . "\n\n"
                    . "Por favor, verifica los cambios en el sistema.\n"
                    . "Gracias por elegirnos.";

                \Mail::to($cliente->email)->send(new \App\Mail\MailGeneral($subject, $message));
            }
        } catch (Exception $e) {
            Log::error('Error enviando correo de MODIFICACIÓN: ' . $e->getMessage());
        }

        $agenda->load([
            'asignacionesResponsables.responsable',
            'asignacionesResponsables.servicio',
            'asignacionesResponsables.cliente'
        ]);

        return response()->json($agenda, 200);

    } catch (Exception $e) {
        DB::rollBack();
        Log::error('💥 Error al modificar la reserva: ' . $e->getMessage());
        return response()->json([
            'error' => 'Error al modificar la reserva',
            'mensaje' => $e->getMessage(),
        ], 500);
    }
}


//cancelar reserva directamente 
public function cancelReservaByAgendaId(int $idAgenda): JsonResponse
{
    DB::beginTransaction();

    $clienteEmail = null;
    $servicioNombre = null;
    $fechaReserva = null;
    $nombreCliente = null;
    $responsableNombre = 'Sin asignar'; 

    try {
        // Encontrar la Agenda
        $agenda = Agenda::find($idAgenda);
        if (!$agenda) {
            return response()->json(['error' => 'Agenda no encontrada.'], 404);
        }
        $fechaReserva = $agenda->fechaInicial . ' ' . $agenda->horaInicial;

        //  Encontrar la Asignacion Responsable
        $asignacion = AsignacionResponsableServicio::where('idAgenda', $idAgenda)->first();
        
        if ($asignacion) {
            $cliente = Tercero::find($asignacion->idCliente);
            $servicio = Servicio::find($asignacion->idServicio);
            $responsable = ResponsableServicio::where('idPersona', $asignacion->idResponsable)->first();

            if ($cliente) {
                $clienteEmail = $cliente->email;
                $nombreCliente = $cliente->nombre ?? $cliente->nombre1;
            }
            if ($servicio) {
                $servicioNombre = $servicio->nombre;
            }
            if ($responsable && $responsable->persona) {
                $responsableNombre = $responsable->persona->nombre;
            }
        }

        if (!$asignacion) {
            $agenda->estado = 'CANCELADO';
            $agenda->save();
            DB::commit();
            if ($clienteEmail && $servicioNombre) {
                $this->sendCancelNotification($clienteEmail, $nombreCliente, $servicioNombre, $fechaReserva, $responsableNombre);
            }
            return response()->json(['success' => true, 'message' => 'Agenda cancelada correctamente (sin asignación).']);
        }


        $detalle = DetalleServicio::find($asignacion->idDetalleServicio);
        
        $idShoppingCart = null;
        if ($detalle) {
            $prestacion = PrestacionServicio::find($detalle->idPrestacionServicio);
            if ($prestacion) {
                $prestacion->estado = 'CANCELADO';
                $prestacion->save();
            }

            $asignacionCarrito = AsignacionCarritoProducto::where('idDetalleServicio', $detalle->id)->first(); 

            if ($asignacionCarrito) {
                $idShoppingCart = $asignacionCarrito->idShoppingCart;
                $shoppingCart = ShoppingCart::find($idShoppingCart);

                if ($shoppingCart) {
                    $shoppingCart->estado = 'CANCELADO';
                    $shoppingCart->save();
                }
            }
        }
        
        //  Cancelar la Agenda
        $agenda->estado = 'CANCELADO';
        $agenda->save();

        DB::commit();

        // Notificación al cliente por Cancelación (USANDO ENVÍO SÍNCRONO)
        if ($clienteEmail && $servicioNombre) {
            try {
                $subject = "Cancelación de tu Reserva: " . e($servicioNombre);
                $message = "¡Hola " . e($nombreCliente) . "!\n\n"
                    . "Tu reserva para el servicio '" . e($servicioNombre) . "' con fecha " . e($fechaReserva) . " y Prestador: " . e($responsableNombre) . " ha sido **CANCELADA** por el administrador.\n\n"
                    . "Si tienes alguna pregunta, por favor, contacta a la administración.\n"
                    . "Gracias.";
    
                \Mail::to($clienteEmail)->send(new \App\Mail\MailGeneral($subject, $message));
            } catch (Exception $e) {
                Log::error('Error enviando correo de CANCELACIÓN: ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true, 
            'message' => 'Reserva cancelada correctamente. Agenda ID: ' . $idAgenda
        ]);

    } catch (Exception $e) {
        DB::rollBack();
        Log::error('💥 Error al cancelar la reserva por ID de Agenda: ' . $e->getMessage());
        return response()->json([
            'error' => 'Ocurrió un error al cancelar el servicio.',
            'exception' => $e->getMessage()
        ], 500);
    }
}
protected function sendCancelNotification(string $email, string $nombreCliente, string $servicioNombre, string $fechaReserva, $responsableNombre): void
{
    try {
        $subject = "Cancelación de tu Reserva: " . e($servicioNombre);
        $message = "¡Hola " . e($nombreCliente) . "!\n\n"
            . "Tu reserva para el servicio '" . e($servicioNombre) . "' con fecha " . e($fechaReserva) . " ha sido **CANCELADA** por el administrador.\n\n"
            . "Si tienes alguna pregunta, por favor, contacta a la administración.\n"
            . "Gracias.";

        GenerarCorreoGeneral::dispatch($email, $subject, $message);
    } catch (Exception $e) {
        Log::error('Error enviando correo de CANCELACIÓN (Job): ' . $e->getMessage());
    }
}

protected function crearNotificacionReservaAdmin($agenda, $servicio, $cliente, $responsable, $companyId)
{
    \Log::info('📩 Datos para crear notificación:', [
        'cliente_id' => $cliente->id ?? 'NULO',
        'responsable_id' => $responsable->id ?? 'NULO',
    ]);

    //  Crear notificación para el ADMINISTRADOR
    try {
        $rolAdmin = Role::where('name', 'administradorVT') 
            ->where('company_id', $companyId) 
            ->first();

        if (!$rolAdmin) {
            $rolAdmin = Role::where('name', 'Admin') 
                ->where('company_id', $companyId) 
                ->first();
        }

        if (!$rolAdmin) {
            \Log::warning("⚠️ No se encontró rol 'administradorVT' o 'Admin' asociado a la empresa ID {$companyId}. No se enviará notificación.");
            return; 
        }
        
        $activationIds = DB::table('model_has_roles')
            ->where('role_id', $rolAdmin->id)
            ->where('model_type', 'App\Models\ActivationCompanyUser')
            ->pluck('model_id')
            ->toArray();

        if (empty($activationIds)) {
            \Log::warning("⚠️ No se encontraron IDs de Activación asociados al Rol ID {$rolAdmin->id}.");
            return; 
        }
        
        $adminUserIds = ActivationCompanyUser::whereIn('id', $activationIds)
            ->pluck('user_id') 
            ->unique() 
            ->toArray();

        if (empty($adminUserIds)) {
            \Log::warning('⚠️ Se encontraron Activaciones, pero no se pudo mapear a ningún Usuario Administrador activo.');
            return;
        }

        \Log::info("✅ Administradores encontrados para la empresa {$companyId}: " . implode(', ', $adminUserIds));


        $usuarioRemitente = null;
        if (!empty($responsable->idPersona)) {
            $usuarioRemitente = User::where('idpersona', $responsable->idPersona)->first();
        }
        $finalRemitenteId = $usuarioRemitente?->id ?? null;


        $reservaData = [
            'agendaId' => $agenda->id,
            'clienteNombre' => $cliente->nombre ?? 'Desconocido',
            'servicioNombre' => $servicio->nombre ?? 'sin nombre',
            'mensaje' => 'Nueva reserva agendada: ' . ($servicio->nombre ?? 'sin nombre'),
        ];
        $fechaReserva = $agenda->fechaInicial . ' ' . $agenda->horaInicial; 

        $mensajeNotificacion = 'Se agendó el servicio "' . ($servicio->nombre ?? 'sin nombre') .
                             '" para ' . ($cliente->nombre ?? 'Desconocido') .
                             ' el día ' . ($fechaReserva ?? 'sin fecha') . '.';

        foreach ($adminUserIds as $userId) {
            // Disparar Evento
            event(new NuevaNotificacionAdmin($userId, $reservaData));

           
            Notificacion::crearNotificacion(
                asunto: '✅ Nueva reserva agendada',
                mensaje: $mensajeNotificacion,
                idUsuarioReceptor: $userId, 
                idUsuarioRemitente: $finalRemitenteId,
                idTipoNotificacion: 1, // Ajusta este ID según tu configuración
                idEmpresa: $companyId,
                route: '/agenda/detalle/' . $agenda->id
            );

            \Log::info("✅ Notificación creada correctamente para admin user_id={$userId}");
        }

    } catch (Exception $e) {
        throw $e; 
    }
}
}