<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Util\KeyUtil;
use Illuminate\Http\Request;
use App\Models\Nomina\HoraExtra;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Nomina\ConfiguracionNomina;
use App\Models\ObservacionSolicitudHoraExtra;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HoraExtraController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $idCompany = KeyUtil::idCompany();
        $horasExtras = HoraExtra::whereHas('contrato', function ($query) use ($idCompany) {
            $query->where('idempresa', $idCompany);
        })->get();
        return response()->json($horasExtras);
    }

    /**
     * Get overtime by idpersona
     * @return JsonResponse|mixed
     */
    public function getMyOvertime(): JsonResponse
    {
        $idPersona = KeyUtil::user()->idpersona;
        $horasExtras = HoraExtra::whereHas('contrato', function ($query) use ($idPersona) {
            $query->where('idpersona', $idPersona);
        })->get();
        return response()->json($horasExtras);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $urlEvidencia = null;
        if ($request->hasFile('documento')) {
            $urlEvidencia = $request
                ->file('documento')
                ->store('horas_extras', ['disk' => 'public']);
        }
        $request->request->add(['urlEvidencia' => $urlEvidencia ?? HoraExtra::RUTA_FOTO_DEFAULT]);
        $request->request->add([
            'idContrato' => $request->idContrato ?? $this->getLastMyContract()?->id,
        ]);
        $horaExtra = HoraExtra::create($request->all());
        return response()->json($horaExtra, 201);
    }

    public function getLastMyContract(): ?Contract
    {
        return Contract::where('idpersona', KeyUtil::user()->idpersona)
            ->latest('id')
            ->first();
    }

    /**
     * Display the specified resource.
     *
     * @param HoraExtra  $horaExtra
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $horaExtra = HoraExtra::findOrFail($id);
        return response()->json($horaExtra);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param HoraExtra  $horaExtra
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        $horaExtra = HoraExtra::findOrFail($id);
        $urlEvidencia = null;
        if ($request->hasFile('documento')) {
            Storage::disk('public')->delete($horaExtra->urlEvidencia);

            $urlEvidencia = $request
                ->file('documento')
                ->store('horas_extras', ['disk' => 'public']);
            $request->request->add(['urlEvidencia' => $urlEvidencia]);
        }
        $horaExtra->update($request->all());
        return response()->json($horaExtra, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param HoraExtra  $horaExtra
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $horaExtra = HoraExtra::findOrFail($id);
        Storage::disk('public')->delete($horaExtra->urlEvidencia);
        $horaExtra->delete();
        return response()->json(null, 204);
    }


    public function storeSolicitudHorasExtra(Request $request)
    {
        try {

            if ($request->filled('idContrato')) {
                $contratoId = $request->idContrato;
            } else {
                try {
                    $contrato = KeyUtil::lastContractActive();
                    $contratoId = $contrato->id;
                } catch (\TypeError $e) {
                    return response()->json([
                        'message' => 'No tiene un contrato activo'
                    ], 400);
                }
            }

            $contrato = Contract::with('salario')->find($contratoId);
            if (!$contrato || !$contrato->salario) {
                return response()->json([
                    'message' => 'El contrato no tiene un salario asociado'
                ], 400);
            }

            $configuracionNomina = ConfiguracionNomina::first();
            if (!$configuracionNomina) {
                return response()->json([
                    'message' => 'No existe configuración de nómina'
                ], 400);
            }


            $valorHoraExtra = $contrato->salario->valor / $configuracionNomina->numHorasMes;

            $horasExtra = new HoraExtra();
            $horasExtra->idConfiguracionHorasExtra = $request->idConfiguracionHorasExtra;
            $horasExtra->numeroHoras = $request->horas;
            $horasExtra->idContrato = $contratoId;
            //falta estado
            $horasExtra->fecha = $request->fecha;
            $horasExtra->valorHoraExtra = $valorHoraExtra;


            if ($request->hasFile('archivo')) {
                $path = $request->file('archivo')->store('archivos_horas_extra', 'public');
                $horasExtra->urlEvidencia = Storage::url($path);
            }

            $horasExtra->save();

            return response()->json([
                'message' => 'Solicitud de horas extra registrada correctamente',
                'data' => $horasExtra
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }



     public function storeSolicitudHorasExtraSupervisor(Request $request)
    {
        try {

            if ($request->filled('idContrato')) {
                $contratoId = $request->idContrato;
            } else {
                try {
                    $contrato = KeyUtil::lastContractActive();
                    $contratoId = $contrato->id;
                } catch (\TypeError $e) {
                    return response()->json([
                        'message' => 'No tiene un contrato activo'
                    ], 400);
                }
            }

            $contrato = Contract::with('salario')->find($contratoId);
            if (!$contrato || !$contrato->salario) {
                return response()->json([
                    'message' => 'El contrato no tiene un salario asociado'
                ], 400);
            }

            $configuracionNomina = ConfiguracionNomina::first();
            if (!$configuracionNomina) {
                return response()->json([
                    'message' => 'No existe configuración de nómina'
                ], 400);
            }


            $valorHoraExtra = $contrato->salario->valor / $configuracionNomina->numHorasMes;

            $horasExtra = new HoraExtra();
            $horasExtra->idConfiguracionHorasExtra = $request->idConfiguracionHorasExtra;
            $horasExtra->numeroHoras = $request->horas;
            $horasExtra->idContrato = $contratoId;
            $horasExtra->estado = 'APROBADO';
        
            $horasExtra->fecha = $request->fecha;
            $horasExtra->valorHoraExtra = $valorHoraExtra;


            if ($request->hasFile('archivo')) {
                $path = $request->file('archivo')->store('archivos_horas_extra', 'public');
                $horasExtra->urlEvidencia = Storage::url($path);
            }

            $horasExtra->save();

            return response()->json([
                'message' => 'Solicitud de horas extra registrada correctamente',
                'data' => $horasExtra
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function getHorasExtraTrabajador()
    {
        $contrato = KeyUtil::lastContractActive();

        if (!$contrato || !$contrato->id) {
            return response()->json([
                'message' => 'No tiene contrato activo'
            ], 404);
        }

        $horasExtras = HoraExtra::with('configuracionHoraExtra')
            ->where('idContrato', $contrato->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($horasExtras);
    }



    public function getHorasExtraSupervisor()
    {
        $horasExtras = HoraExtra::with('configuracionHoraExtra', 'contrato.persona')->get();

        return response()->json($horasExtras);
    }



    public function getCommentsSolicitudHorasExtra(Request $request): JsonResponse
    {
        $observaciones = ObservacionSolicitudHoraExtra::with(['usuario.persona'])
            ->where('idHoraExtra', $request->idSolicitud)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($observaciones);
    }


    public function storeCommentHoraExtra(Request $request): JsonResponse
    {
        $request->request->add(['fecha' => $request->fecha ?? now()]);
        $request->request->add(['idUsuario' => KeyUtil::user()->id]);
        $observacion = ObservacionSolicitudHoraExtra::create($request->all());
        return response()->json($observacion->load('usuario.persona'), 201);
    }



    public function solicitudHoraExtraUpdateBySupervisor(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'estado' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $solicitud = HoraExtra::findOrFail($id);

            $solicitud->update([
                'estado'               => $validated['estado'],
                'idContratoSupervisor' => KeyUtil::lastContractActive()->id,
            ]);

            $observacion = ObservacionSolicitudHoraExtra::create([
                'fecha'       => now(),
                'observacion' => $request->comentario ?? 'Estado: ' . $validated['estado'] . ' - Observación realizada',
                'idHoraExtra' => $id,
                'idUsuario'   => KeyUtil::user()->id,
            ]);

            DB::commit();

            $solicitud->observacion = $observacion;

            return response()->json($solicitud, 200);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();

            return response()->json([
                'error' => 'Hubo un problema al procesar la solicitud. Intente nuevamente.',
            ], 500);
        }
    }
}
