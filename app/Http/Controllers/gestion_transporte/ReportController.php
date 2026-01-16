<?php

namespace App\Http\Controllers\gestion_transporte;

use App\Util\KeyUtil;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transporte\Viaje;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Jobs\EnviarFacturaJob;
use App\Models\AsignacionDescuentoPlanilla;
use App\Models\Caja;
use App\Models\EstadoViaje;
use App\Models\FacturaElectronica;
use App\Models\Ruta;
use App\Models\Tercero;
use App\Models\Transporte\AgendarViaje;
use App\Models\Transporte\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader as PdfParserStreamReader;
use setasign\Fpdi\PdfReader\StreamReader;

class ReportController extends Controller
{

    


    private string $note = "'El pasajero debe estar atento al despacho del vehículo. No se reembolsa en caso de pérdida del viaje'";

    /**
     * View pdf ticket
     * @return Response
     */



public function generatePDFTicket(Request $request): Response
{
    $idViaje = $request->idViaje;
    $user = KeyUtil::user();
    $idCompany = KeyUtil::idCompany();

    $company = Company::find($idCompany);
    $viaje = Viaje::with(['ruta.ciudadOrigen', 'ruta.ciudadDestino', 'vehiculo.asignacionPropietario.afiliacion'])
        ->findOrFail($idViaje);

    $agenda = AgendarViaje::where('idViaje', $idViaje)->first();
    $horaSalida = $agenda?->hora ? Carbon::createFromFormat('H:i:s', $agenda->hora)->format('h:i A') : 'N/A';
    $fechaImpresion = now()->format('M d/Y H:i');

    $ruta = Ruta::find($request->idRuta);
    $precioRuta = $ruta->precio ?? 0;

    $baseData = [
        'nit'            => $company->nit ?? 'N/A',
        'fecha'          => optional($viaje->created_at)->format('Y/m/d') ?? 'N/A',
        'ciudadOrigen'   => $viaje->ruta->ciudadOrigen->descripcion ?? 'N/A',
        'agencia'        => $company->razonSocial ?? 'N/A',
        'despachador'    => trim(($user->persona->nombre1 ?? '') . ' ' . ($user->persona->apellido1 ?? '')),
        'horaSalida'     => $horaSalida,
        'ruta'           => "{$viaje->ruta->ciudadOrigen->descripcion} - {$viaje->ruta->ciudadDestino->descripcion}",
        'tarifa'         => $precioRuta,
        'vehiculo'       => $viaje->vehiculo->asignacionPropietario->afiliacion->numero ?? 'N/A',
        'placa'          => $viaje->vehiculo->placa ?? 'N/A',
        'numeroPasajes'  => $viaje->numeroPasajes ?? 'N/A',
        'numeroPuesto'   => $viaje->numeroPuesto ?? 'N/A',
        'aseguradora'    => 'EQUIDAD',
        'noPoliza'       => 'A807987',
        'fechaImpresion' => $fechaImpresion,
        'nota'           => $this->note ?? 'N/A',
    ];

    if ($request->has('ticketIds')) {
        $ticketIds = is_string($request->ticketIds)
            ? explode(',', $request->ticketIds)
            : (array) $request->ticketIds;

        $tickets = Ticket::with('tercero')
            ->whereIn('id', $ticketIds)
            ->orderBy('id', 'asc')
            ->get();

        if ($tickets->isEmpty()) {
            abort(404, 'No se encontraron los tickets especificados');
        }

        $pdfs = [];
        $pageWidth = 226;
        $pageHeight = 400;

        foreach ($tickets as $ticketItem) {
            $data = array_merge($baseData, [
                'numeroTicket' => $ticketItem->numeroTicket,
                'puesto'       => $ticketItem->puesto ?? 'N/A',
                'pasajero'     => $ticketItem->tercero->nombre ?? 'N/A',
                'cantidad'     => $ticketItem->cantidad,
                'valorTotal'   => $precioRuta * $ticketItem->cantidad,
            ]);

            $pdfs[] = PDF::loadView('transporte.tickete-viaje', $data)
                ->setPaper([0, 0, $pageWidth, $pageHeight])
                ->output();
        }

        $merged = new \setasign\Fpdi\Fpdi();
        foreach ($pdfs as $file) {
            $pageCount = $merged->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($file));
            for ($page = 1; $page <= $pageCount; $page++) {
                $tplIdx = $merged->importPage($page);
                $merged->AddPage('P', [$pageWidth, $pageHeight]);
                $merged->useTemplate($tplIdx, 0, 0, $pageWidth, $pageHeight);
            }
        }

        return response($merged->Output('S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="tickets.pdf"');
    }

    $ticket = Ticket::with('tercero')
        ->where('idViaje', $viaje->id)
        ->orderBy('id', 'desc')
        ->firstOrFail();

    $data = array_merge($baseData, [
        'numeroTicket' => $ticket->numeroTicket,
        'puesto'       => $ticket->puesto ?? 'N/A',
        'pasajero'     => $ticket->tercero->nombre ?? 'N/A',
        'cantidad'     => $ticket->cantidad,
        'valorTotal'   => $precioRuta * $ticket->cantidad,
    ]);

    $pdf = PDF::loadView('transporte.tickete-viaje', $data)
        ->setPaper([0, 0, 226, 400]);

    return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="ticket.pdf"');
}




public function generatePDFTicketByCC(Request $request)
{
    $cc = $request->cc;

    $user = KeyUtil::user();
    $idCompany = KeyUtil::idCompany();
    $company = Company::where('id', $idCompany)->first();

    $tercero = Tercero::where('identificacion', $cc)->first();

    if (!$tercero) {
        return response()->json(['error' => 'El tercero con esa cédula no existe'], 404);
    }

    $ticket = Ticket::with(['viaje.ruta.ciudadOrigen', 'viaje.ruta.ciudadDestino', 'tercero', 'viaje.vehiculo'])
        ->where('idTercero', $tercero->id)
        ->when($request->idTicket, function ($query) use ($request) {
            $query->where('id', $request->idTicket);
        })
        ->first();

    if (!$ticket) {
        return response()->json(['error' => 'El tercero no tiene tickets asociados'], 404);
    }

    $viaje = $ticket->viaje;

    if (!$viaje) {
        return response()->json(['error' => 'El viaje asociado al ticket no existe'], 404);
    }

    $agenda = AgendarViaje::whereHas('tickets', function ($query) use ($ticket) {
        $query->where('id', $ticket->id);
    })
        ->where('idViaje', $viaje->id)
        ->first();

    if (!$agenda) {
        return response()->json(['error' => 'No se encontró agenda asociada al viaje'], 404);
    }

    $horaSalida = Carbon::createFromFormat('H:i:s', $agenda->hora)->format('h:i A');
    $fechaActual = Carbon::now();
    $fechaImpresion = $fechaActual->format('M d/Y H:i');
    $total = $ticket->cantidad;

    // =========================================================
    // 🔹 BLOQUE: FACTURA ELECTRÓNICA (CUFE + QR)
    // =========================================================
    $facturaElectronica = FacturaElectronica::where('ticket_id', $ticket->id)
        ->where('idEmpresa', $idCompany)
        ->first();
        $qrPath = null;
        $cufe = null;

        if ($facturaElectronica) {
            $cufe = $facturaElectronica->cufe;

            if ($facturaElectronica->qr_image) {
                $fileName = basename($facturaElectronica->qr_image);
                $fullPath = public_path('storage/qr-factura-electronica/' . $fileName);

                if (file_exists($fullPath)) {
                    $qrPath = $fullPath;
                }
            }
        }


        //print_r($qrPath);

    // =========================================================
    // 🔹 Datos para el PDF
    // =========================================================
    $data = [
        'nit'            => $company->nit,
        'fecha'          => Carbon::parse($viaje->created_at)->format('Y/m/d'),
        'numeroTicket'   => rand(100000, 999999),
        'ciudadOrigen'   => $viaje->ruta->ciudadOrigen->descripcion,
        'agencia'        => $company->razonSocial,
        'despachador'    => $user->persona->nombre1 . ' ' . $user->persona->apellido1,
        'horaSalida'     => $horaSalida,
        'ruta'           => $viaje->ruta->ciudadOrigen->descripcion . ' - ' . $viaje->ruta->ciudadDestino->descripcion,
        'tarifa'         => $viaje->ruta->precio,
        'vehiculo'       => $viaje->vehiculo->asignacionPropietario->afiliacion->numero,
        'placa'          => $viaje->vehiculo->placa,
        'numeroPasajes'  => 14,
        'numeroPuesto'   => 5,
        'pasajero'       => $ticket->tercero->nombre,
        'puesto'         => 13,
        'aseguradora'    => 'EQUIDAD',
        'noPoliza'       => 'A807987',
        'fechaImpresion' => $fechaImpresion,
        'nota'           => $this->note,
        'cantidad'       => $total,
        
        'cufe'           => $cufe,
        'qrPath'         => $qrPath,
    ];

    // =========================================================
    // 🔹 Generar PDF
    // =========================================================
    $pdf = PDF::loadView('transporte.tickete-viaje-copia', $data)
        ->setPaper([0, 0, 226, 400]); // tamaño de ticket

    return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="ticket.pdf"');
}



    public function getTicketsByCC(Request $request)
    {
        $cc = $request->cc;

        $tercero = Tercero::where('identificacion', $cc)->first();

        if (!$tercero) {
            return response()->json(['error' => 'El tercero con esa cédula no existe'], 404);
        }

        $tickets = Ticket::with(['viaje.ruta.ciudadOrigen', 'viaje.ruta.ciudadDestino'])
            ->where('idTercero', $tercero->id)
            ->orderByDesc('created_at')
            ->take(4)
            ->get();

        if ($tickets->isEmpty()) {
            return response()->json(['error' => 'El tercero no tiene tickets asociados'], 404);
        }

        return response()->json([
            'tercero' => $tercero,
            'tickets' => $tickets
        ]);
    }


    /**
     * Send ticket to email
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function sendTicketEmail(Request $request): JsonResponse
    {

        $user = KeyUtil::user();

        $data =  [
            'nit'          => 123123123,
            'fecha'        => now()->format('d/m/Y'),
            'numeroTicket' => rand(100000, 999999),
            'agencia'      => 'Terminal Central',
            'despachador'  => $user->persona->nombre1 . ' ' . $user->persona->apellido1,
            'horaSalida'   => '12:30 PM',
            'ruta'         => 'Ruta 45 - Popayán',
            'tarifa'       => 7,
            500,
            'vehiculo'     => '7129',
            'placa'        => 'SAQ941',
            'pasajero'     => 'Juan Pérez',
            'puesto'       => 13,
            'aseguradora'  => 'EQUIDAD',
            'noPoliza'     => 'A807987',
            'nota'         => $this->note,
        ];

        $pdf = PDF::loadView('transporte.tickete-viaje', $data)->setPaper([0, 0, 226, 400]);

        // Mail::to($request->email)->send(new TicketMail($pdf->output()));

        return response()->json(['message' => 'Tiquete enviado con éxito']);
    }

    /**
     * Generate pdf planilla view
     * @return Response
     */
public function generatePDFPlanilla(Request $request): Response
{
    $idViaje = $request->idViaje;
    $user = KeyUtil::user();
    $idCompany = KeyUtil::idCompany();
    $company = Company::find($idCompany);

    $viaje = Viaje::with([
        'ruta.ciudadOrigen',
        'ruta.ciudadDestino',
        'vehiculo.asignacionPropietario.propietario',
        'conductor.persona'
    ])->findOrFail($idViaje);

    $ticket = Ticket::where('idViaje', $viaje->id)
        ->when($request->idTicket, fn($query) => $query->where('id', $request->idTicket))
        ->first();

    $agenda = null;
    if ($ticket) {
        $agenda = AgendarViaje::whereHas('tickets', fn($query) => $query->where('id', $ticket->id))
            ->where('idViaje', $idViaje)
            ->first();
    }

    $horaSalida = $agenda
        ? Carbon::createFromFormat('H:i:s', $agenda->hora)->format('h:i A')
        : '00:00';

    Carbon::setLocale('es');
    $fechaActual = Carbon::now();
    $fechaFormateada = $fechaActual->translatedFormat('M d/Y');
    $fechaImpresion = $fechaActual->translatedFormat('M d/Y h:i A');

    $ticketsVendidos = Ticket::where('idViaje', $viaje->id)
        ->whereIn('estado', ['VENDIDO', 'PORDESPACHAR'])
        ->with('ruta')
        ->get();

    $valorTotal = $ticketsVendidos->sum(fn($t) => ($t->ruta->precio ?? 0) * ($t->cantidad ?? 1));

    $descuentos = AsignacionDescuentoPlanilla::where('idViaje', $idViaje)
        ->with('descuento')
        ->get();

    $totalDeducciones = $descuentos->sum('valor');
    $recaudoEfectivo = $valorTotal - $totalDeducciones;

    $valorTotalFormateado = '$' . number_format($valorTotal, 0, ',', '.');
    $totalDeduccionesFormateado = '$' . number_format($totalDeducciones, 0, ',', '.');
    $recaudoFormateado = '$' . number_format($recaudoEfectivo, 0, ',', '.');

    $data = [
        'nit'              => $company->nit ?? 'N/A',
        'fecha'            => $fechaFormateada,
        'numeroPlanilla'   => $viaje->numeroPlanillaViaje ?? 'N/A', // ← AQUÍ EL CAMBIO
        'agencia'          => $company->razonSocial ?? 'N/A',
        'despachador'      => (($user->persona->nombre1 ?? '') . ' ' . ($user->persona->apellido1 ?? '')) ?: 'N/A',
        'horaSalida'       => $horaSalida ?? 'N/A',
        'ruta'             => (($viaje->ruta->ciudadOrigen->descripcion ?? 'N/A') . '-' . ($viaje->ruta->ciudadDestino->descripcion ?? 'N/A')),
        'tarifa'           => $viaje->ruta->precio ?? 0,
        'valor'            => $valorTotalFormateado,
        'vehiculo'         => $viaje->vehiculo->asignacionPropietario->afiliacion->numero ?? 'N/A',
        'placa'            => $viaje->vehiculo->placa ?? 'N/A',
        'pasajero'         => $ticket->tercero->nombre ?? 'N/A',
        'puesto'           => 14,
        'aseguradora'      => 'EQUIDAD',
        'noPoliza'         => 'A807987',
        'propietario'      => (($viaje->vehiculo->asignacionPropietario->propietario->nombre1 ?? ' ') . ' ' . ($viaje->vehiculo->asignacionPropietario->propietario->apellido1 ?? '')) ?: 'N/A',
        'motorista'        => (($viaje->conductor->persona->nombre1 ?? '') . ' ' . ($viaje->conductor->persona->apellido1 ?? '')) ?: 'N/A',
        'fechaImpresion'   => $fechaImpresion,
        'nota'             => $this->note ?? 'N/A',
        'descuentos'       => $descuentos,
        'totalDeducciones' => $totalDeduccionesFormateado,
        'recaudoEfectivo'  => $recaudoFormateado,
    ];

    $pdf = PDF::loadView('transporte.planilla-viaje', $data)
        ->setPaper([0, 0, 226, 400]);

    return $pdf->stream('planilla.pdf');
}



    /**
     * Send planilla email
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function sendPlanillaEmail(Request $request): JsonResponse
    {

        $user = KeyUtil::user();


        $data =  [
            'nit'          => 123123123,
            'fecha'        => now()->format('d/m/Y'),
            'numeroTicket' => rand(100000, 999999),
            'agencia'      => 'Terminal Central',
            'despachador'  => $user->persona->nombre1 . ' ' . $user->persona->apellido1,
            'horaSalida'   => '12:30 PM',
            'ruta'         => 'Ruta 45 - Popayán',
            'tarifa'       => 7,
            500,
            'vehiculo'     => '7129',
            'placa'        => 'SAQ941',
            'pasajero'     => 'Juan Pérez',
            'puesto'       => 13,
            'aseguradora'  => 'EQUIDAD',
            'noPoliza'     => 'A807987',
            'nota'         => $this->note,
        ];

        $pdf = PDF::loadView('transporte.planilla-viaje', $data)->setPaper([0, 0, 226, 400]);

        // Mail::to($request->email)->send(new TicketMail($pdf->output()));

        return response()->json(['message' => 'Planilla enviada con éxito']);
    }




    public function getPlanillasByUser($idCaja)
    {
        $user = KeyUtil::user();

        $caja = Caja::where('id', $idCaja)
            ->where('idUsuario', $user->id)
            ->first();

        if (!$caja) {
            return response()->json([
                'message' => 'No se encontró una caja válida para este usuario.'
            ], 404);
        }

        $viajes = EstadoViaje::with([
            'viaje.tickets.ruta.ciudadOrigen',
            'viaje.tickets.ruta.ciudadDestino',
            'viaje.tickets.ruta.lugar'
        ])
            ->whereIn('estado', ['EN VIAJE', 'PLANILLA', 'PENDIENTE'])
            ->whereHas('viaje.tickets', function ($query) use ($idCaja) {
                $query->where('idCaja', $idCaja);
            })
            ->get();

        $valorTiqueteado    = 0;
        $valorDespachado    = 0;
        $valorPorDespachar  = 0;

        $totalTickets       = 0;
        $totalDespachado    = 0;
        $totalPorDespachar  = 0;

        $viajes->each(function ($estadoViaje) use (
            &$valorTiqueteado,
            &$valorDespachado,
            &$valorPorDespachar,
            &$totalTickets,
            &$totalDespachado,
            &$totalPorDespachar,
            $idCaja
        ) {
            if ($estadoViaje->viaje && $estadoViaje->viaje->tickets) {

                $ticketsCaja = $estadoViaje->viaje->tickets->where('idCaja', $idCaja);

                $agrupado = $ticketsCaja
                    ->groupBy('ruta.idRutaPadre')
                    ->map(function ($tickets) use (
                        &$valorTiqueteado,
                        &$valorDespachado,
                        &$valorPorDespachar,
                        &$totalTickets,
                        &$totalDespachado,
                        &$totalPorDespachar
                    ) {
                        $totalTicketsRuta = $tickets->sum('cantidad');
                        $totalTickets += $totalTicketsRuta;

                        $totalDespachadoRuta   = $tickets->where('estado', 'VENDIDO')->sum('cantidad');
                        $totalPorDespacharRuta = $tickets->where('estado', 'PORDESPACHAR')->sum('cantidad');

                        $totalDespachado   += $totalDespachadoRuta;
                        $totalPorDespachar += $totalPorDespacharRuta;

                        $ruta = $tickets->first()->ruta;
                        $rutaPrincipal = $ruta->idRutaPadre
                            ? Ruta::with(['ciudadOrigen', 'ciudadDestino'])->find($ruta->idRutaPadre)
                            : $ruta;

                        $valorTotalRuta = $tickets->sum(fn($ticket) => $ticket->cantidad * ($ticket->ruta->precio ?? 0));
                        $valorTiqueteado += $valorTotalRuta;

                        $valorDespachadoRuta = $tickets->where('estado', 'VENDIDO')
                            ->sum(fn($ticket) => $ticket->cantidad * ($ticket->ruta->precio ?? 0));
                        $valorPorDespacharRuta = $tickets->where('estado', 'PORDESPACHAR')
                            ->sum(fn($ticket) => $ticket->cantidad * ($ticket->ruta->precio ?? 0));

                        $valorDespachado   += $valorDespachadoRuta;
                        $valorPorDespachar += $valorPorDespacharRuta;

                        $lugaresConTickets = $tickets->groupBy('ruta.idLugar')->map(function ($ticketsLugar) {
                            $ruta = $ticketsLugar->first()->ruta;
                            $cantidadTickets = $ticketsLugar->sum('cantidad');
                            $precioUnitario  = $ruta->precio ?? 0;

                            $nombreLugar = $ruta->lugar->nombre
                                ?? ($ruta->ciudadOrigen->descripcion . ' - ' . $ruta->ciudadDestino->descripcion);

                            $estado = $ticketsLugar->first()->estado ?? null;

                            return [
                                'idLugar'         => $ruta->lugar->id ?? null,
                                'nombre'          => $nombreLugar,
                                'tipoLugar'       => $ruta->lugar->tipoLugar ?? 'RUTA',
                                'cantidadTickets' => $cantidadTickets,
                                'precio'          => $precioUnitario,
                                'valorTotalLugar' => $cantidadTickets * $precioUnitario,
                                'estado'          => $estado
                            ];
                        })->values();

                        return [
                            'idRutaPadre'           => $ruta->idRutaPadre,
                            'nombreRuta'            => $rutaPrincipal->ciudadOrigen->descripcion . ' - ' . $rutaPrincipal->ciudadDestino->descripcion,
                            'totalTicketsRuta'      => $totalTicketsRuta,
                            'totalDespachado'       => $totalDespachadoRuta,
                            'totalPorDespachar'     => $totalPorDespacharRuta,
                            'valorTotalRuta'        => $valorTotalRuta,
                            'valorDespachadoRuta'   => $valorDespachadoRuta,
                            'valorPorDespacharRuta' => $valorPorDespacharRuta,
                            'lugares'               => $lugaresConTickets
                        ];
                    })->values();

                $estadoViaje->viaje->rutas = $agrupado;
                unset($estadoViaje->viaje->tickets);
            }
        });

        return response()->json([
            'viajes'             => $viajes,
            // 'caja'               => $caja,
            'totalTickets'       => $totalTickets,
            'totalDespachado'    => $totalDespachado,
            'totalPorDespachar'  => $totalPorDespachar,
            'valorTiqueteado'    => $valorTiqueteado,
            'valorDespachado'    => $valorDespachado,
            'valorPorDespachar'  => $valorPorDespachar
        ]);
    }

    public function getPlanillasDespachadasUser($idCaja)
    {
        $user = KeyUtil::user();

        if (!$idCaja) {
            return response()->json([
                'message' => 'Debe enviar el parámetro idCaja.'
            ], 400);
        }

        $caja = Caja::where('id', $idCaja)
            ->where('idUsuario', $user->id)
            ->first();

        if (!$caja) {
            return response()->json([
                'message' => 'No se encontró una caja válida para este usuario.'
            ], 404);
        }

        $estadosViaje = EstadoViaje::with([
            'viaje.tickets.ruta.ciudadOrigen',
            'viaje.tickets.ruta.ciudadDestino',
            'viaje.tickets.ruta.lugar'
        ])
            ->whereIn('estado', ['EN VIAJE', 'PLANILLA', 'PENDIENTE'])
            ->whereHas('viaje.tickets', function ($query) use ($idCaja) {
                $query->where('idCaja', $idCaja);
            })
            ->get();

        $planillas = $estadosViaje->map(function ($estadoViaje) use ($idCaja) {
            $viaje = $estadoViaje->viaje;
            $tickets = $viaje?->tickets?->where('idCaja', $idCaja) ?? collect();

            if ($tickets->isEmpty()) return null;

            $ruta = $tickets->first()->ruta;

            $rutaPrincipal = $ruta?->idRutaPadre
                ? Ruta::with(['ciudadOrigen', 'ciudadDestino'])->find($ruta->idRutaPadre)
                : $ruta;

            return [
                'idEstadoViaje'   => $estadoViaje->id,
                'numeroPlanilla'  => $viaje->numeroPlanillaViaje ?? null,
                'trayecto'        => ($rutaPrincipal?->ciudadOrigen?->descripcion ?? 'N/A')
                    . ' - ' .
                    ($rutaPrincipal?->ciudadDestino?->descripcion ?? 'N/A'),
                'cantidadTickets' => $tickets->sum('cantidad'),
                'vehiculo'        => $viaje?->vehiculo?->asignacionPropietario?->afiliacion?->numero ?? 'N/A',
            ];
        })->filter()->values();

        return response()->json([
            'planillas' => $planillas
        ]);
    }





    public function generateAlcoholimetriaConductor(Request $request)
    {
        $idViaje = $request->idViaje;

        $user = KeyUtil::user();

        $viaje = Viaje::with([
            'ruta.ciudadOrigen',
            'ruta.ciudadDestino',
            'vehiculo.asignacionPropietario.propietario',
            'vehiculo.asignacionPropietario.afiliacion',
            'conductor.persona'
        ])->findOrFail($idViaje);

        $agendamiento = DB::table('agendarviajes')
            ->where('idViaje', $idViaje)
            ->orderBy('id', 'desc')
            ->first();

        $data = [
            'trayecto'    => $viaje->ruta?->ciudadOrigen?->descripcion . ' - ' . $viaje->ruta?->ciudadDestino?->descripcion ?? 'N/A',
            'despachador' => ($user->persona?->apellido1 ?? '') . ' ' . ($user->persona?->nombre1 ?? ''),

            'hora'  => $agendamiento
                ? \Carbon\Carbon::parse($agendamiento->hora)->format('g:i A')
                : 'N/A',

            'fecha' => $agendamiento
                ? \Carbon\Carbon::parse($agendamiento->fecha)->format('d/m/Y')
                : 'N/A',
            'planilla'     => $viaje->numeroPlanillaViaje ?? 'N/A',

            'vehiculo'    => $viaje->vehiculo?->asignacionPropietario?->afiliacion?->numero ?? 'N/A',
            'placa'       => $viaje->vehiculo?->placa ?? 'N/A',

            'motorista'   => ($viaje->conductor?->persona?->apellido1 ?? '') . ' ' . ($viaje->conductor?->persona?->nombre1 ?? 'N/A'),
            'propietario' => ($viaje->vehiculo?->asignacionPropietario?->propietario?->apellido1 ?? '') . ' ' . ($viaje->vehiculo?->asignacionPropietario?->propietario?->nombre1 ?? 'N/A'),
        ];


        $pdf = PDF::loadView('transporte.alcoholimetria-conductor', $data)
            ->setPaper([0, 0, 226, 800]);


        return $pdf->stream('alcoholimetro_conductor.pdf');
    }


}
