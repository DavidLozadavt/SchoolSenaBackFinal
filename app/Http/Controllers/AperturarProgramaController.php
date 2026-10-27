<?php

namespace App\Http\Controllers;

use App\Http\Resources\AperturarProgramaResource;
use App\Models\AperturarPrograma;
use App\Models\Programa;
use App\Models\TipoGrado;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AperturarProgramaController extends Controller
{
    public function index(): JsonResponse
    {
        $data = AperturarPrograma::with(['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre', 'cortes'])->latest()->get();

        return response()->json(AperturarProgramaResource::collection($data));
    }

    public function indexTipoGrado(): JsonResponse
    {
        $data = TipoGrado::all();

        return response()->json($data);
    }

    public function aperturasDisponibles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idPrograma' => 'required|exists:programa,id',
            'idSede' => 'nullable|exists:sedes,id'
        ]);

        $data = AperturarPrograma::with(['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre', 'cortes'])
            ->latest()
            ->where('fechaFinalPlanMejoramiento', '>=', Carbon::today()->toDateString())
            ->where('idPrograma', $validated['idPrograma'])
            ->when(isset($validated['idSede']), function ($query) use ($validated) {
                $query->where('idSede', $validated['idSede']);
            })
            ->get();

        return response()->json(AperturarProgramaResource::collection($data));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'observacion' => 'nullable|string|max:1000',
            'nombre' => 'required|string|max:255',
            'idTipoGrado' => 'required|exists:tipoGrado,id',
            'idPeriodo' => 'required|exists:periodo,id',
            'idPrograma' => 'required|exists:programa,id',
            'estado' => 'required|in:ACTIVO,INACTIVO,OCULTO,PENDIENTE,RECHAZADO,APROBADO,CANCELADO,REPROBADO,CERRADO,ACEPTADO,LEIDO,EN ESPERA,INSCRIPCION,MATRICULADO,ABIERTO,EN CURSO,POR ACTUALIZAR,CURSANDO,ENTREVISTA,SIN ENTREVISTA,JUSTIFICADO',
            'idSede' => 'required|exists:sedes,id',
            'fechaInicialClases' => 'required|date',
            'fechaFinalClases' => 'required|date|after_or_equal:fechaInicialClases',
            'fechaInicialInscripciones' => 'required|date',
            'fechaFinalInscripciones' => 'required|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'required|date',
            'fechaFinalMatriculas' => 'required|date|after_or_equal:fechaInicialMatriculas',
            'fechaInicialPlanMejoramiento' => 'required|date',
            'fechaFinalPlanMejoramiento' => 'required|date|after_or_equal:fechaInicialPlanMejoramiento',
            'tipoCalificacion' => 'required|in:NUMERICO,DESEMPEÑO',
            'pension' => 'nullable|boolean',
            'valorPension' => 'nullable|numeric',
            'diasMoraMatricula' => 'nullable|integer',
            'porcentajeMoraPension' => 'nullable|numeric',
            'diaCobro' => 'nullable|integer',
            'idJornada' => 'required|exists:jornadas,id'
        ]);
        return DB::transaction(function () use ($validated, $request) {
            $apertura = AperturarPrograma::create($validated);

            if (isset($request->cortes) && is_array($request->cortes)) {
                foreach ($request->cortes as $corteData) {
                    $apertura->cortes()->create([
                        'numero' => $corteData['numero'],
                        'fechaInicial' => $corteData['fechaInicial'],
                        'fechaFinal' => $corteData['fechaFinal'],
                        'porcentaje' => $corteData['porcentaje'],
                    ]);
                }
            }

            return response()->json([
                'message' => 'Programa aperturado correctamente',
                'data' => $apertura
            ], 201);
        });
    }


    public function jornadasParaGrados(): JsonResponse
    {
        $jornadas = DB::table('jornadas')
            ->where('estado', 'Activo')
            ->orderBy('nombreJornada')
            ->get(['id', 'nombreJornada']);

        $unicas = [];
        foreach ($jornadas as $jornada) {
            $nombre = mb_strtoupper(trim((string) $jornada->nombreJornada));
            if ($nombre === '' || isset($unicas[$nombre])) {
                continue;
            }
            $unicas[$nombre] = [
                'id' => $jornada->id,
                'nombre' => $jornada->nombreJornada,
            ];
        }

        return response()->json(array_values($unicas));
    }

    public function aperturaParaGrados(int $idPrograma): JsonResponse
    {
        $programa = Programa::where('id', $idPrograma)
            ->where('idCompany', KeyUtil::idCompany())
            ->first();

        if (!$programa) {
            return response()->json([
                'message' => 'El programa no pertenece a la empresa con la que entraste',
            ], 404);
        }

        $apertura = AperturarPrograma::with(['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre'])
            ->where('idPrograma', $programa->id)
            ->latest('id')
            ->first();

        if (!$apertura) {
            $idSede = $programa->idSede
                ?: DB::table('sedes')->where('idEmpresa', KeyUtil::idCompany())->value('id');
            $idPeriodo = DB::table('periodo')->where('idEmpresa', KeyUtil::idCompany())->orderByDesc('id')->value('id')
                ?: DB::table('periodo')->orderByDesc('id')->value('id');

            if (!$idSede || !$idPeriodo) {
                return response()->json([
                    'message' => 'El programa no tiene sede o periodo para crear los grados',
                ], 422);
            }

            $inicio = now()->toDateString();
            $fin = now()->addYear()->toDateString();
            $apertura = AperturarPrograma::create([
                'observacion' => 'Apertura para grados',
                'idPeriodo' => $idPeriodo,
                'idPrograma' => $programa->id,
                'estado' => 'ACTIVO',
                'idSede' => $idSede,
                'tipoCalificacion' => 'NUMERICO',
                'fechaInicialClases' => $inicio,
                'fechaFinalClases' => $fin,
                'fechaInicialInscripciones' => $inicio,
                'fechaFinalInscripciones' => $fin,
                'fechaInicialMatriculas' => $inicio,
                'fechaFinalMatriculas' => $fin,
                'fechaInicialPlanMejoramiento' => $inicio,
                'fechaFinalPlanMejoramiento' => $fin,
            ]);
            $apertura->load(['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre']);
        }

        return response()->json(new AperturarProgramaResource($apertura));
    }

    public function show($id): JsonResponse
    {
        $relaciones = ['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre'];
        if (Schema::hasTable('cortes')) {
            $relaciones[] = 'cortes';
        }
        if (Schema::hasColumn('aperturarprograma', 'idTipoGrado')) {
            $relaciones[] = 'grado:id,nombreTipoGrado';
        }
        $apertura = AperturarPrograma::with($relaciones)->findOrFail($id);

        return response()->json(new AperturarProgramaResource($apertura));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $apertura = AperturarPrograma::findOrFail($id);

        $validated = $request->validate([
            'observacion' => 'nullable|string|max:1000',
            'nombre' => 'nullable|string|max:255',
            'idTipoGrado' => 'nullable|exists:tipoGrado,id',
            'idPeriodo' => 'nullable|exists:periodo,id',
            'idPrograma' => 'nullable|exists:programa,id',
            'estado' => 'nullable|in:ACTIVO,INACTIVO,OCULTO,PENDIENTE,RECHAZADO,APROBADO,CANCELADO,REPROBADO,CERRADO,ACEPTADO,LEIDO,EN ESPERA,INSCRIPCION,MATRICULADO,ABIERTO,EN CURSO,POR ACTUALIZAR,CURSANDO,ENTREVISTA,SIN ENTREVISTA,JUSTIFICADO',
            'idSede' => 'nullable|exists:sedes,id',
            'fechaInicialClases' => 'nullable|date',
            'fechaFinalClases' => 'nullable|date|after_or_equal:fechaInicialClases',
            'fechaInicialInscripciones' => 'nullable|date',
            'fechaFinalInscripciones' => 'nullable|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'nullable|date',
            'fechaFinalMatriculas' => 'nullable|date|after_or_equal:fechaInicialMatriculas',
            'fechaInicialPlanMejoramiento' => 'nullable|date',
            'fechaFinalPlanMejoramiento' => 'nullable|date|after_or_equal:fechaInicialPlanMejoramiento',
            'tipoCalificacion' => 'nullable|in:NUMERICO,DESEMPEÑO',
            //Diferecnia al sena:
            'pension' => 'nullable|boolean',
            'valorPension' => 'nullable|numeric',
            'diasMoraMatricula' => 'nullable|integer',
            'porcentajeMoraPension' => 'nullable|numeric',
            'diaCobro' => 'nullable|integer',
            //Nuevo campo
            'idJornada' => 'nullable|exists:jornadas,id'
        ]);

        $apertura->update($validated);

        // Manejar cortes: Eliminar los existentes y crear los nuevos
        if (isset($request->cortes) && is_array($request->cortes)) {
            // Eliminar cortes existentes
            $apertura->cortes()->delete();

            // Crear nuevos cortes
            foreach ($request->cortes as $corteData) {
                $apertura->cortes()->create([
                    'numero' => $corteData['numero'],
                    'fechaInicial' => $corteData['fechaInicial'],
                    'fechaFinal' => $corteData['fechaFinal'],
                    'porcentaje' => $corteData['porcentaje'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Registro actualizado correctamente',
            'data' => $apertura
        ]);
    }
}
