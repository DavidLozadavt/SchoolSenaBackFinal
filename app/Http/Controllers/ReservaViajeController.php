<?php

namespace App\Http\Controllers;

use App\Models\ReservaViaje;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Transporte\Viaje;
use App\Util\KeyUtil;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;



class ReservaViajeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ReservaViaje::with(['viaje', 'tercero', 'agendaViaje', 'ruta']);

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('idTercero')) {
            $query->where('idTercero', $request->idTercero);
        }

        if ($request->has('codigo')) {
            $query->where('codigo', $request->codigo);
        }

        $reservas = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($reservas);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return response()->json([
            'estados' => ['VENDIDO', 'PORDESPACHAR', 'RESERVADO', 'REDIMIDO']
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'idViaje' => 'required|exists:viajes,id',
            'idTercero' => 'required|exists:tercero,id',
            'idAgendaViaje' => 'nullable|exists:agendarviajes,id',
            'idRuta' => 'nullable|exists:rutas,id',
            'cantidad' => 'required|integer|min:1',
            'estado' => 'required|in:VENDIDO,PORDESPACHAR,RESERVADO,REDIMIDO',
            'valor' => 'nullable|numeric|min:0'
        ]);

        DB::beginTransaction();

        try {
            $validated['codigo'] = $this->generarCodigoUnico();

            $reserva = ReservaViaje::create($validated);

            $qrData = json_encode([
                'codigoReserva' => $reserva->codigo,
                'pasajero'      => $reserva->tercero->nombre ?? 'N/A',
                'ruta'          => optional($reserva->viaje?->ruta)->descripcion ?? 'N/A',
                'fechaReserva'  => optional($reserva->created_at)->format('Y-m-d') ?? 'N/A',
                'estado'        => $reserva->estado,
            ]);

            $qrSvg = QrCode::format('svg')
                ->size(150)
                ->margin(1)
                ->generate($qrData);

            $qrPath = 'storage/qrcodes/qr-' . $reserva->codigo . '.svg';
            Storage::disk('public')->put($qrPath, $qrSvg);

            $reserva->update(['qrPath' => $qrPath]);

            DB::commit();

            return response()->json([
                'message' => 'Reserva creada exitosamente',
                'data' => $reserva->load(['viaje', 'tercero', 'agendaViaje', 'ruta'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ReservaViaje  $reservaViaje
     * @return \Illuminate\Http\Response
     */
    public function show(ReservaViaje $reservaViaje)
    {
        return response()->json([
            'data' => $reservaViaje->load(['viaje', 'tercero', 'agendaViaje', 'ruta'])
        ]);
    }

    /**
     * Buscar reserva por código
     *
     * @param  string  $codigo
     * @return \Illuminate\Http\Response
     */
    // public function buscarPorCodigo($codigo)
    // {
    //     $reserva = ReservaViaje::where('codigo', $codigo)
    //         ->with(['viaje', 'tercero', 'agendaViaje', 'ruta.ciudadOrigen', 'ruta.ciudadDestino'])
    //         ->first();

    //     if (!$reserva) {
    //         return response()->json([
    //             'message' => 'Reserva no encontrada'
    //         ], 404);
    //     }

    //     return response()->json([
    //         'data' => $reserva
    //     ]);
    // }

    public function buscarPorCodigo(Request $request, $codigo)
    {
        $reserva = ReservaViaje::with(['viaje.ruta', 'tercero'])
            ->where('codigo', $codigo)
            ->first();

        if (!$reserva) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una reserva con ese código.'
            ], 404);
        }

        if ($reserva->estado === 'REDIMIDO') {
            return response()->json([
                'success' => false,
                'message' => 'Este ticket ya fue redimido anteriormente.',
                'data' => $reserva
            ], 400);
        }

        if ($request->has('idViaje')) {
            $viajeActual = Viaje::with('ruta')->find($request->idViaje);

            if (!$viajeActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'El viaje especificado no existe.'
                ], 404);
            }

            if ($viajeActual->idRuta !== $reserva->viaje->idRuta) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ticket pertenece a una ruta diferente y no puede ser redimido en este viaje.',
                    'rutaTicket' => $reserva->viaje->ruta->nombre ?? null,
                    'rutaViaje' => $viajeActual->ruta->nombre ?? null,
                ], 400);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reserva encontrada correctamente.',
            'data' => $reserva
        ]);
    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ReservaViaje  $reservaViaje
     * @return \Illuminate\Http\Response
     */
    public function edit(ReservaViaje $reservaViaje)
    {
        return response()->json([
            'data' => $reservaViaje->load(['viaje', 'tercero', 'agendaViaje', 'ruta']),
            'estados' => ['VENDIDO', 'PORDESPACHAR', 'RESERVADO', 'REDIMIDO']
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ReservaViaje  $reservaViaje
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ReservaViaje $reservaViaje)
    {
        $validated = $request->validate([
            'idViaje' => 'nullable|exists:viajes,id',
            'idTercero' => 'nullable|exists:tercero,id',
            'idAgendaViaje' => 'nullable|exists:agendarviajes,id',
            'idRuta' => 'nullable|exists:rutas,id',
            'cantidad' => 'nullable|integer|min:1',
            'estado' => 'in:VENDIDO,PORDESPACHAR,RESERVADO,REDIMIDO',
            'valor' => 'nullable|numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            $reservaViaje->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Reserva actualizada exitosamente',
                'data' => $reservaViaje->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /** * Remove the specified resource from storage.
     *
     * @param  \App\Models\ReservaViaje  $reservaViaje
     * @return \Illuminate\Http\Response
     */
    public function destroy(ReservaViaje $reservaViaje)
    {
        try {
            $reservaViaje->delete();

            return response()->json([
                'message' => 'Reserva eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar código único aleatorio de 10 caracteres
     *
     * @return string
     */
    private function generarCodigoUnico()
    {
        do {
            // Generar código alfanumérico de 10 caracteres
            $codigo = strtoupper(Str::random(10));
        } while (ReservaViaje::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Generar código QR para la reserva
     *
     * @param  \App\Models\ReservaViaje  $reservaViaje
     * @return \Illuminate\Http\Response
     */
    public function generarQR(ReservaViaje $reservaViaje)
    {

        return response()->json([
            'codigo' => $reservaViaje->codigo,
            'url_qr' => route('reservas.buscar-codigo', $reservaViaje->codigo)
        ]);
    }

    /**
     * Cambiar estado de la reserva
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ReservaViaje  $reservaViaje
     * @return \Illuminate\Http\Response
     */
    public function cambiarEstado(Request $request, ReservaViaje $reservaViaje)
    {
        $validated = $request->validate([
            'estado' => 'required|in:VENDIDO,PORDESPACHAR,RESERVADO'
        ]);

        $reservaViaje->update(['estado' => $validated['estado']]);

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'data' => $reservaViaje
        ]);
    }

    /**
     * Generar PDF tipo ticket para la reserva
     *
     * @param  int  $idReserva
     * @return \Illuminate\Http\Response
     */

    public function generatePDFReserva($idReserva): \Illuminate\Http\Response
    {
        $idCompany = KeyUtil::idCompany();
        $user = KeyUtil::user();

        $company = Company::find($idCompany);
        $reserva = ReservaViaje::with(['viaje.ruta.ciudadOrigen', 'viaje.ruta.ciudadDestino', 'tercero'])
            ->findOrFail($idReserva);

        if (!$company) {
            $company = (object) [
                'razonSocial' => 'TAX BELALCAZAR',
                'nit' => '123456789-0',
                'rutaLogoUrl' => asset('default/logoweb.png')
            ];
        }

        $logoPath = null;
        if ($company->rutaLogoUrl) {
            $fileName = basename($company->rutaLogoUrl);
            $fullPath = public_path('storage/company/' . $fileName);

            if (file_exists($fullPath)) {
                $logoPath = $fullPath;
            } else {
                $logoPath = public_path('default/logoweb.png');
            }
        }

        $qrPath = 'qrcodes/qr-' . $reserva->codigo . '.svg';
        if (!Storage::disk('public')->exists($qrPath)) {
            $qrData = json_encode([
                'codigoReserva' => $reserva->codigo,
                'pasajero'      => $reserva->tercero->nombre ?? 'N/A',
                'ruta'          => optional($reserva->viaje->ruta)->ciudadOrigen->descripcion
                    . ' - ' . optional($reserva->viaje->ruta)->ciudadDestino->descripcion,
                'fechaReserva'  => optional($reserva->created_at)->format('Y-m-d'),
                'estado'        => $reserva->estado,
            ]);

            $qrSvg = QrCode::format('svg')->size(150)->margin(1)->generate($qrData);
            Storage::disk('public')->put($qrPath, $qrSvg);
        }

        $qrPublicPath = public_path('storage/' . $qrPath);

        $data = [
            'nit'            => $company->nit ?? 'N/A',
            'fecha'          => optional($reserva->created_at)->format('Y/m/d') ?? 'N/A',
            'ciudadOrigen'   => $reserva->viaje?->ruta?->ciudadOrigen?->descripcion ?? 'N/A',
            'agencia'        => $company->razonSocial ?? 'N/A',
            'despachador'    => trim(($user->persona->nombre1 ?? '') . ' ' . ($user->persona->apellido1 ?? '')),
            'horaSalida'     => optional($reserva->viaje)->horaSalida ?? 'N/A',
            'ruta'           => $reserva->viaje?->ruta
                ? "{$reserva->viaje->ruta->ciudadOrigen->descripcion} - {$reserva->viaje->ruta->ciudadDestino->descripcion}"
                : 'N/A',
            'tarifa'         => optional($reserva->viaje->ruta)->precio ?? 0,
            'pasajero'       => $reserva->tercero->nombre ?? 'N/A',
            'cantidad'       => $reserva->cantidad,
            'valorTotal'     => (optional($reserva->viaje->ruta)->precio ?? 0) * $reserva->cantidad,
            'codigoReserva'  => $reserva->codigo ?? 'N/A',
            'estado'         => $reserva->estado,
            'fechaImpresion' => now()->format('Y-m-d H:i:s'),
            'nota'           => 'Este ticket debe presentarse al abordar el vehículo.',
            'logoPath'       => $logoPath,
            'qrPath'         => $qrPublicPath,
        ];


        $pageWidth = 226;
        $pageHeight = 600;

        $pdf = Pdf::loadView('transporte.tikete-reserva', $data)
            ->setPaper([0, 0, $pageWidth, $pageHeight])
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="reserva-' . $reserva->codigo . '.pdf"');
    }

    public function updateEstadoReservaRedimido(Request $request, $idReserva)
    {
        $reserva = ReservaViaje::find($idReserva);

        if (!$reserva) {
            return response()->json([
                'success' => false,
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $cantidadRedimir = $request->input('cantidad', 1);

        if ($reserva->cantidad <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La reserva ya fue completamente redimida.'
            ], 400);
        }

        if ($cantidadRedimir > $reserva->cantidad) {
            return response()->json([
                'success' => false,
                'message' => 'La cantidad a redimir excede la disponible.'
            ], 400);
        }

        $reserva->cantidad -= $cantidadRedimir;

        if ($reserva->cantidad == 0) {
            $reserva->estado = 'REDIMIDO';
        }

        $reserva->save();

        return response()->json([
            'success' => true,
            'message' => $reserva->estado === 'REDIMIDO'
                ? 'Reserva completamente redimida.'
                : 'Redención parcial registrada.',
            'data' => $reserva
        ]);
    }
}
