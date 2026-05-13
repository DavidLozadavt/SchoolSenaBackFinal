<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ficha;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstructorLiderController extends Controller
{
    public function getFichasLider(Request $request)
    {
        $user = KeyUtil::user();

        $contrato = $user->persona->contrato->first();

        if (!$contrato) {
            return response()->json([
                'message' => 'El usuario no tiene contrato'
            ], 404);
        }

        $fichas = Ficha::where('idInstructorLider', $contrato->id)
            ->with(['asignacion.programa', 'jornada', 'sede'])
            ->get();

        return response()->json($fichas);
    }

    public function getAprendicesFicha(int $idFicha)
    {
        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

        // Intentar obtener de matriculaAcademica primero
        $maQuery = DB::table($tableMa)
            ->where('idFicha', $idFicha)
            ->select('idMatricula', 'id')
            ->distinct();
        
        $ma = $maQuery->get();

        if ($ma->isEmpty()) {
            // Fallback a matricula directa
            $matriculasIds = DB::table('matricula')
                ->where('idFicha', $idFicha)
                ->whereIn('estado', ['ACTIVO', 'MATRICULADO', 'CURSANDO', 'EN FORMACION'])
                ->pluck('id');
            
            if ($matriculasIds->isNotEmpty()) {
                $ma = DB::table($tableMa)
                    ->whereIn('idMatricula', $matriculasIds)
                    ->select('idMatricula', 'id')
                    ->distinct()
                    ->get();
            }
        }

        $idsMatricula = $ma->pluck('idMatricula')->unique()->filter()->values();

        $aprendices = DB::table('matricula as m')
            ->join('persona as p', 'm.idPersona', '=', 'p.id')
            ->leftJoin('usuario as u', 'p.id', '=', 'u.idpersona')
            ->whereIn('m.id', $idsMatricula)
            ->select([
                'm.id as idMatricula',
                'p.id as idPersona',
                'p.identificacion',
                DB::raw("TRIM(CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.nombre2,''), ' ', COALESCE(p.apellido1,''), ' ', COALESCE(p.apellido2,''))) as nombreCompleto"),
                'p.rutaFoto',
                'u.email',
                'm.estado as estadoMatricula'
            ])
            ->get();

        return response()->json($aprendices);
    }
}
