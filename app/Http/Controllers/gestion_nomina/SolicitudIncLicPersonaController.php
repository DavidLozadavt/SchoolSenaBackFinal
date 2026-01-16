<?php

namespace App\Http\Controllers\gestion_nomina;


use App\Http\Controllers\Controller;
use App\Models\ConfiguracionIncapacidadLicencia;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\ObservacionSolicitudIncLicPer;
use App\Models\Nomina\SolicitudIncLicPersona;
use App\Models\Nomina\TipoIncapacidad;
use App\Util\KeyUtil;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SolicitudIncLicPersonaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $search            = $request->search;
        $fechaSolicitud    = $request->fechaSolicitud;
        $fechaInicial      = $request->fechaInicial;
        $fechaFinal        = $request->fechaFinal;
        $idTipoIncapacidad = $request->idTipoIncapacidad;
        $numDias           = $request->numDias;
        $valor             = $request->valor;
        $estado            = $request->estado;
        $totalData         = $request->totalData;

        $allSolicitusBySupervisor = SolicitudIncLicPersona::filterAdvanceSolicitudIncLic(
            $search,
            $fechaSolicitud,
            $fechaInicial,
            $fechaFinal,
            $idTipoIncapacidad,
            $numDias,
            $valor,
            $estado,
        )
            ->with(['contrato.persona.usuario', 'observaciones', 'tipoIncapacidad'])
            ->orderBy('id', 'desc')
            ->paginate($totalData ?? 25);


        $idsSolicitudesExtendidas = SolicitudIncLicPersona::whereNotNull('idSolicitudPrincipal')
            ->pluck('idSolicitudPrincipal')
            ->unique()
            ->toArray();

        $solicitudes = $allSolicitusBySupervisor->getCollection()->map(function ($solicitud) use ($idsSolicitudesExtendidas) {
            $solicitud->noExtender = in_array($solicitud->id, $idsSolicitudesExtendidas);
            return $solicitud;
        });


        $allSolicitusBySupervisor->setCollection($solicitudes);

        return response()->json([
            'total'       => $allSolicitusBySupervisor->total(),
            'solicitudes' => $allSolicitusBySupervisor->items(),
        ], 200);
    }


    /**
     * Get solicitudes by worker last contract
     * @return JsonResponse
     */
    public function getMySolicitudIncLicPersona(): JsonResponse
    {
        $mySolicitudesInc = SolicitudIncLicPersona::where("idContrato", KeyUtil::lastContractActive()->id)
            ->with('tipoIncapacidad')
            ->orderBy('id', 'desc')
            ->get();

        $idsSolicitudPrincipal = $mySolicitudesInc
            ->pluck('idSolicitudPrincipal')
            ->filter()
            ->toArray();


        $mySolicitudesInc->transform(function ($solicitud) use ($idsSolicitudPrincipal) {
            $solicitud->noExtender = in_array($solicitud->id, $idsSolicitudPrincipal);
            return $solicitud;
        });

        return response()->json($mySolicitudesInc);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $urlSoporte = null;

        if ($request->hasFile('soporte')) {
            $urlSoporte = $request
                ->file('soporte')
                ->store('solicitudes_inc_lic_personas', ['disk' => 'public']);
        }

        $request->request->add(['urlSoporte' => $urlSoporte ?? SolicitudIncLicPersona::RUTA_FOTO_DEFAULT]);
        $request->request->add(['idContrato' => KeyUtil::lastContractActive()->id]);
        $request->request->add(['fechaSolicitud' => now()]);

        $numDias = Carbon::parse($request->fechaFinal)
            ->diffInDays(Carbon::parse($request->fechaInicial)) + 1;

        $request->request->add(['numDias' => $numDias]);

        $configuracion = ConfiguracionIncapacidadLicencia::first();

        if (!$configuracion || !$configuracion->diasNinez) {
            return response()->json(['error' => 'No se ha configurado el número de días para CUIDADO_NINEZ.'], 400);
        }

        $maxDiasPermitidos = (int) $configuracion->diasNinez;

        $nomina = Nomina::where('idContrato', $request->idContrato)->latest()->first();

        if (!$nomina) {
            return response()->json([
                'message' => 'El contrato debe tener la configuración de nómina antes de registrar la solicitud.'
            ], 422);
        }


        $tipoCuidadoNinez = TipoIncapacidad::where('tipoIncapacidad', 'CUIDADO_NINEZ')->first();

        if ($tipoCuidadoNinez && $request->idTipoIncapacidad == $tipoCuidadoNinez->id) {

            $anioSolicitud = Carbon::parse($request->fechaInicial)->year;

            $solicitudes = SolicitudIncLicPersona::where('idContrato', $request->idContrato)
                ->where('idTipoIncapacidad', $tipoCuidadoNinez->id)
                ->whereYear('fechaInicial', $anioSolicitud)
                ->get();

            $diasUsados = 0;
            foreach ($solicitudes as $solicitud) {
                $inicio = new DateTime($solicitud->fechaInicial);
                $fin = new DateTime($solicitud->fechaFinal);
                $dias = $inicio->diff($fin)->days + 1;
                $diasUsados += $dias;
            }

            $totalPropuesto = $diasUsados + $numDias;

            if ($totalPropuesto > $maxDiasPermitidos) {
                return response()->json([
                    'error' => "No se puede registrar la solicitud. El contrato ya ha usado {$diasUsados} días de {$maxDiasPermitidos} permitidos para CUIDADO_NINEZ en el año {$anioSolicitud}.",
                    'dias_usados' => $diasUsados,
                    'dias_propuestos' => $numDias,
                    'dias_maximos' => $maxDiasPermitidos,
                    'dias_restantes' => $maxDiasPermitidos - $diasUsados
                ], 400);
            }
        }


        $existeAceptada = SolicitudIncLicPersona::where('idContrato', $request->idContrato)
            ->where('idTipoIncapacidad', $request->idTipoIncapacidad)
            ->where('estado', SolicitudIncLicPersona::ACEPTADO)
            ->exists();

        if ($existeAceptada) {
            return response()->json([
                'message' => 'Ya existe una incapacidad ACEPTADA con este tipo para este contrato.'
            ], 422);
        }

        $request->request->add(['valor' => $this->getTotalValue($nomina, $numDias)]);

        $solicitud = SolicitudIncLicPersona::create($request->all());

        ObservacionSolicitudIncLicPer::create([
            'fecha'       => now(),
            'idSolicitud' => $solicitud->id,
            'observacion' => $request->comentario,
            'idUsuario'   => KeyUtil::user()->id,
        ]);

        return response()->json($solicitud->load(['observaciones']), 201);
    }





    public function solicitudIncBySupervisor(Request $request): JsonResponse
    {
        $urlSoporte = null;

        if ($request->hasFile('soporte')) {
            $urlSoporte = $request
                ->file('soporte')
                ->store('solicitudes_inc_lic_personas', ['disk' => 'public']);
        }

        $request->request->add([
            'urlSoporte' => $urlSoporte ?? SolicitudIncLicPersona::RUTA_FOTO_DEFAULT,
            'idContrato' => $request->idContrato,
            'fechaSolicitud' => now(),
        ]);


        $fechaInicial = Carbon::parse($request->fechaInicial);
        $fechaFinal = Carbon::parse($request->fechaFinal);

        if ($fechaFinal->lt($fechaInicial)) {
            return response()->json([
                'message' => 'La fecha final no puede ser menor que la fecha inicial.',
                'error' => true,
            ], 422);
        }

        $numDias = $fechaFinal->diffInDays($fechaInicial) + 1;
        $request->request->add(['numDias' => $numDias]);

        $nomina = Nomina::where('idContrato', $request->idContrato)->latest()->first();
        $request->request->add(['valor' => $this->getTotalValue($nomina, $numDias)]);
        $request->request->add(['estado' => $request->estado ?? SolicitudIncLicPersona::ACEPTADO]);
        $request->request->add(['idContratoSupervisor' => KeyUtil::lastContractActive()->id]);

        $existe = SolicitudIncLicPersona::where('idContrato', $request->idContrato)
            ->where('idTipoIncapacidad', $request->idTipoIncapacidad)
            ->where('estado', SolicitudIncLicPersona::ACEPTADO)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe una incapacidad ACEPTADA con este tipo para este contrato.',
                'error' => true,
            ], 422);
        }

        $solicitud = SolicitudIncLicPersona::create($request->all());

        ObservacionSolicitudIncLicPer::create([
            'fecha'       => now(),
            'idSolicitud' => $solicitud->id,
            'observacion' => $request->comentario,
            'idUsuario'   => KeyUtil::user()->id,
        ]);

        return response()->json($solicitud->load(['observaciones']), 201);
    }




    /**
     * Update status only request inc by supervisor
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return JsonResponse|mixed
     */
    public function solicitudIncUpdateBySupervisor(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'estado' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $solicitud = SolicitudIncLicPersona::findOrFail($id);

            $existeAceptada = SolicitudIncLicPersona::where('idContrato', $solicitud->idContrato)
                ->where('idTipoIncapacidad', $solicitud->idTipoIncapacidad)
                ->where('estado', SolicitudIncLicPersona::ACEPTADO)
                ->where('id', '<>', $id)
                ->exists();

            if ($existeAceptada && $validated['estado'] == SolicitudIncLicPersona::ACEPTADO) {
                return response()->json([
                    'message' => 'Ya existe una incapacidad ACEPTADA con este tipo para este contrato.'
                ], 422);
            }

            $solicitud->update([
                'estado'               => $validated['estado'],
                'idContratoSupervisor' => KeyUtil::lastContractActive()->id,
            ]);

            $observacion = ObservacionSolicitudIncLicPer::create([
                'fecha'       => now(),
                'observacion' => $request->comentario,
                'idSolicitud' => $id,
                'idUsuario'   => KeyUtil::user()->id,
            ]);

            DB::commit();

            $solicitud->observacion = $observacion;

            return response()->json($solicitud, 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Hubo un problema al procesar la solicitud. Intente nuevamente.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get total value to request disabilities
     * @param \App\Models\Nomina\Nomina $payroll
     * @param int|float $numDays
     * @return float|int
     */
    private function getTotalValue(Nomina $payroll, int|float $numDays): float|int
    {
        if ($payroll) {
            $valHourBase = $payroll->valHorasBase;

            $valDay = $valHourBase * 8;

            $valueTotal = $valDay * $numDays;

            return $valueTotal;
        }
        return 0;
    }

    /**
     * Display the specified resource.
     *
     * @param  SolicitudIncLicPersona  $solicitudIncLicPersona
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $solicitudIncLic = SolicitudIncLicPersona::findOrFail($id);
        return response()->json($solicitudIncLic->load(['observaciones']));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  SolicitudIncLicPersona  $solicitudIncLicPersona
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $solicitudIncLic = SolicitudIncLicPersona::findOrFail($id);
        $urlSoporte = null;

        if ($request->hasFile('soporte')) {
            Storage::disk('public')->delete($solicitudIncLic->urlSoporte);

            $urlSoporte = $request
                ->file('soporte')
                ->store('solicitudes_inc_lic_personas', ['disk' => 'public']);

            $request->request->add(['urlSoporte' => $urlSoporte]);
        }

        if ($request->filled(['fechaInicial', 'fechaFinal'])) {
            $fechaInicial = Carbon::parse($request->fechaInicial);
            $fechaFinal = Carbon::parse($request->fechaFinal);

            if ($fechaFinal->lt($fechaInicial)) {
                return response()->json([
                    'message' => 'La fecha final no puede ser menor que la fecha inicial.',
                    'error' => true,
                ], 422);
            }

            $numDias = $fechaFinal->diffInDays($fechaInicial) + 1;
        } else {
            $numDias = $solicitudIncLic->numDias;
        }

        $request->request->add(['numDias' => $numDias]);

        $idContrato = $solicitudIncLic->idContrato;
        $nomina = Nomina::where('idContrato', $idContrato)->latest()->first();

        if (!$nomina) {
            return response()->json([
                'message' => 'El contrato debe tener la configuración de nómina antes de registrar la solicitud.'
            ], 422);
        }

        $configuracion = ConfiguracionIncapacidadLicencia::first();

        if (!$configuracion || !$configuracion->diasNinez) {
            return response()->json(['error' => 'No se ha configurado el número de días para CUIDADO_NINEZ.'], 400);
        }

        $maxDiasPermitidos = (int) $configuracion->diasNinez;

        $tipoCuidadoNinez = TipoIncapacidad::where('tipoIncapacidad', 'CUIDADO_NINEZ')->first();

        $idTipoIncapacidad = $request->idTipoIncapacidad ?? $solicitudIncLic->idTipoIncapacidad;

        if ($tipoCuidadoNinez && $idTipoIncapacidad == $tipoCuidadoNinez->id) {
            $anioSolicitud = Carbon::parse($request->fechaInicial ?? $solicitudIncLic->fechaInicial)->year;

            $solicitudes = SolicitudIncLicPersona::where('idContrato', $idContrato)
                ->where('idTipoIncapacidad', $tipoCuidadoNinez->id)
                ->whereYear('fechaInicial', $anioSolicitud)
                ->where('id', '<>', $solicitudIncLic->id)
                ->get();

            $diasUsados = 0;
            foreach ($solicitudes as $solicitud) {
                $inicio = new DateTime($solicitud->fechaInicial);
                $fin = new DateTime($solicitud->fechaFinal);
                $dias = $inicio->diff($fin)->days + 1;
                $diasUsados += $dias;
            }

            $totalPropuesto = $diasUsados + $numDias;

            if ($totalPropuesto > $maxDiasPermitidos) {
                return response()->json([
                    'error' => "No se puede actualizar la solicitud. El contrato ya ha usado {$diasUsados} días de {$maxDiasPermitidos} permitidos para CUIDADO_NINEZ en el año {$anioSolicitud}.",
                    'dias_usados' => $diasUsados,
                    'dias_propuestos' => $numDias,
                    'dias_maximos' => $maxDiasPermitidos,
                    'dias_restantes' => $maxDiasPermitidos - $diasUsados
                ], 400);
            }
        }


        $existeAceptada = SolicitudIncLicPersona::where('idContrato', $idContrato)
            ->where('idTipoIncapacidad', $idTipoIncapacidad)
            ->where('estado', SolicitudIncLicPersona::ACEPTADO)
            ->where('id', '<>', $solicitudIncLic->id)
            ->exists();

        if ($existeAceptada) {
            return response()->json([
                'message' => 'Ya existe una incapacidad ACEPTADA con este tipo para este contrato.'
            ], 422);
        }

        $request->request->add(['valor' => $this->getTotalValue($nomina, $numDias)]);

        $solicitudIncLic->update($request->all());

        ObservacionSolicitudIncLicPer::create([
            'fecha'       => now(),
            'idSolicitud' => $solicitudIncLic->id,
            'observacion' => $request->comentario,
            'idUsuario'   => KeyUtil::user()->id,
        ]);

        return response()->json($solicitudIncLic->load('observaciones'), 200);
    }




    /**
     * Remove the specified resource from storage.
     *
     * @param  SolicitudIncLicPersona  $solicitudIncLicPersona
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $solicitudIncLic = SolicitudIncLicPersona::findOrFail($id);
        Storage::disk('public')->delete($solicitudIncLic->urlSoporte);
        $solicitudIncLic->delete();
        return response()->json(null, 204);
    }



    public function extederIncapcidadPersonaBySupervisor(Request $request)
    {
        $urlSoporte = null;

        if ($request->hasFile('soporte')) {
            $urlSoporte = $request
                ->file('soporte')
                ->store('solicitudes_inc_lic_personas', ['disk' => 'public']);
        }

        $request->merge([
            'urlSoporte' => $urlSoporte ?? SolicitudIncLicPersona::RUTA_FOTO_DEFAULT,
            'idContrato' => $request->idContrato,
            'fechaSolicitud' => now(),
        ]);

        $numDias = Carbon::parse($request->fechaFinal)->diffInDays(Carbon::parse($request->fechaInicial));
        $request->merge(['numDias' => $numDias]);

        $nomina = Nomina::where('idContrato', $request->idContrato)->latest()->first();
        $request->merge([
            'valor' => $this->getTotalValue($nomina, $numDias),
            'estado' => $request->estado ?? SolicitudIncLicPersona::ACEPTADO,
            'idContratoSupervisor' => KeyUtil::lastContractActive()->id,
        ]);

        if ($request->filled('idSolicitudPrincipal')) {
            $solicitudBase = SolicitudIncLicPersona::find($request->idSolicitudPrincipal);

            if ($solicitudBase) {
                $nuevoIdSolicitudPrincipal = $solicitudBase->idSolicitudPrincipal ?? $solicitudBase->id;

                $request->merge([
                    'idSolicitud' => $solicitudBase->id,
                    'idSolicitudPrincipal' => $nuevoIdSolicitudPrincipal
                ]);

                $solicitudBase->update(['noExtender' => true]);
            }
        }

        $existe = SolicitudIncLicPersona::where('idContrato', $request->idContrato)
            ->where('idTipoIncapacidad', $request->idTipoIncapacidad)
            ->where('estado', SolicitudIncLicPersona::ACEPTADO)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe una incapacidad ACEPTADA con este tipo para este contrato.',
                'error' => true,
            ], 422);
        }

        $solicitud = SolicitudIncLicPersona::create($request->all());

        ObservacionSolicitudIncLicPer::create([
            'fecha'       => now(),
            'idSolicitud' => $solicitud->id,
            'observacion' => $request->comentario,
            'idUsuario'   => KeyUtil::user()->id,
        ]);

        return response()->json($solicitud->load(['observaciones']), 201);
    }




    public function extederIncapcidadPersonaByTrabajador(Request $request)
    {
        $urlSoporte = null;

        if ($request->hasFile('soporte')) {
            $urlSoporte = $request
                ->file('soporte')
                ->store('solicitudes_inc_lic_personas', ['disk' => 'public']);
        }

        $request->merge([
            'urlSoporte' => $urlSoporte ?? SolicitudIncLicPersona::RUTA_FOTO_DEFAULT,
            'idContrato' => KeyUtil::lastContractActive()->id,
            'fechaSolicitud' => now(),
        ]);

        $numDias = Carbon::parse($request->fechaFinal)->diffInDays(Carbon::parse($request->fechaInicial));
        $request->merge(['numDias' => $numDias]);

        $nomina = Nomina::where('idContrato', $request->idContrato)->latest()->first();

        if (!$nomina) {
            return response()->json([
                'message' => 'El contrato debe tener la configuración de nómina antes de registrar la solicitud.'
            ], 422);
        }

        $existeAceptada = SolicitudIncLicPersona::where('idContrato', $request->idContrato)
            ->where('idTipoIncapacidad', $request->idTipoIncapacidad)
            ->where('estado', SolicitudIncLicPersona::ACEPTADO)
            ->exists();

        if ($existeAceptada) {
            return response()->json([
                'message' => 'Ya existe una incapacidad ACEPTADA con este tipo para este contrato.'
            ], 422);
        }

        if ($request->filled('idSolicitudPrincipal')) {
            $solicitudBase = SolicitudIncLicPersona::find($request->idSolicitudPrincipal);

            if ($solicitudBase) {
                $nuevoIdSolicitudPrincipal = $solicitudBase->idSolicitudPrincipal ?? $solicitudBase->id;

                $request->merge([
                    'idSolicitud' => $solicitudBase->id,
                    'idSolicitudPrincipal' => $nuevoIdSolicitudPrincipal
                ]);
            }
        }

        $request->merge(['valor' => $this->getTotalValue($nomina, $numDias)]);

        $solicitud = SolicitudIncLicPersona::create($request->all());

        ObservacionSolicitudIncLicPer::create([
            'fecha'       => now(),
            'idSolicitud' => $solicitud->id,
            'observacion' => $request->comentario,
            'idUsuario'   => KeyUtil::user()->id,
        ]);

        return response()->json($solicitud->load(['observaciones']), 201);
    }




    public function getTrazabilidadSolicitudIncLicPersona($id)
    {
        $solicitudes = SolicitudIncLicPersona::with('tipoIncapacidad')
            ->where('idSolicitudPrincipal', $id)
            ->get();

        if ($solicitudes->isEmpty()) {
            return response()->json(['message' => 'No se encontró información para esta solicitud.'], 404);
        }

        return response()->json($solicitudes);
    }




    public function calculateDaysRestantesCuidadoNinez(Request $request)
    {
        $idContrato = $request->idContrato;
        $fecha = $request->fechaInicial ?? now();
        $anio = Carbon::parse($fecha)->year;

        $configuracion = ConfiguracionIncapacidadLicencia::first();

        if (!$idContrato) {
            return response()->json(['error' => 'Debe proporcionar un idContrato.'], 400);
        }

        if (!$configuracion || !$configuracion->diasNinez) {
            return response()->json(['error' => 'No se ha configurado el número de días para CUIDADO_NINEZ.'], 400);
        }

        $maxDiasPermitidos = (int) $configuracion->diasNinez;

        $tipo = TipoIncapacidad::where('tipoIncapacidad', 'CUIDADO_NINEZ')->first();

        if (!$tipo) {
            return response()->json(['error' => 'No existe el tipo de incapacidad CUIDADO_NINEZ.'], 404);
        }


        $solicitudes = SolicitudIncLicPersona::where('idContrato', $idContrato)
            ->where('idTipoIncapacidad', $tipo->id)
            ->whereYear('fechaInicial', $anio)
            ->get();

        $diasUsados = 0;

        foreach ($solicitudes as $solicitud) {
            $inicio = new DateTime($solicitud->fechaInicial);
            $fin = new DateTime($solicitud->fechaFinal);
            $dias = $inicio->diff($fin)->days + 1;
            $diasUsados += $dias;
        }

        $diasRestantes = max(0, $maxDiasPermitidos - $diasUsados);

        if ($diasUsados >= $maxDiasPermitidos) {
            return response()->json([
                'error' => "El contrato ya ha alcanzado el máximo de {$maxDiasPermitidos} días permitidos para CUIDADO_NINEZ en el año {$anio}.",
                'dias_usados' => $diasUsados,
                'dias_restantes' => 0
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "El contrato ha usado {$diasUsados} días de {$maxDiasPermitidos} permitidos para CUIDADO_NINEZ en el año {$anio}.",
            'dias_usados' => $diasUsados,
            'dias_restantes' => $diasRestantes,
            'anio' => $anio
        ]);
    }
}