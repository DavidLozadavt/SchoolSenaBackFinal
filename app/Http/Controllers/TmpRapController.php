<?php


namespace App\Http\Controllers;

use App\Models\ArchivoRap;
use Illuminate\Http\Request;
use App\Util\KeyUtil;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TmpRapController extends Controller
{
    public function uploadRaps(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        $idCompany     = KeyUtil::idCompany();
        $idUser        = KeyUtil::user()->id;
        $archivo       = $request->file('archivo');
        $spreadsheet   = IOFactory::load($archivo->getPathname());
        $hoja          = $spreadsheet->getActiveSheet();
        $numeroDeFicha = $hoja->getCell('C3')->getValue();
        $fechaReporte  = $hoja->getCell('C2')->getValue();
        $filaInicial   = 14;

        $fechaReporteFormateada = DateTime::createFromFormat('d/m/Y', $fechaReporte);
        $fechaReporteFormateada = ($fechaReporteFormateada !== false)
            ? $fechaReporteFormateada->format('Y-m-d')
            : (new DateTime())->format('Y-m-d');

        $fichaValida = DB::table('ficha')
            ->where('id', $request->input('idFicha'))
            ->where('codigo', $numeroDeFicha)
            ->exists();

        if (!$fichaValida) {
            return response()->json([
                'error' => 'El reporte cargado no corresponde a la ficha seleccionada.'
            ], 422);
        }

        $registroExistente = DB::table('archivosRap')
            ->where('idFicha', $request->input('idFicha'))
            ->orderBy('fechaReporte', 'desc')
            ->first();

        if ($registroExistente && $fechaReporteFormateada <= $registroExistente->fechaReporte) {
            return response()->json([
                'error' => "La fecha del documento debe ser mayor a la última fecha cargada ('{$registroExistente->fechaReporte}')."
            ], 422);
        }

        DB::beginTransaction();

        try {
            $dataBatch = [];
            $batchSize = 500;

            foreach ($hoja->getRowIterator($filaInicial) as $fila) {
                $estado          = $hoja->getCell('E' . $fila->getRowIndex())->getValue();

                if (strtoupper(trim($estado)) === 'INDUCCION') {
                    DB::rollBack();
                    return response()->json([
                        'error' => "La ficha {$numeroDeFicha} esta en estado 'INDUCCIÓN'. No se pueden procesar datos para este estado."
                    ], 422);
                }
                $tipoDoc         = $hoja->getCell('A' . $fila->getRowIndex())->getValue();
                $numeroDoc       = $hoja->getCell('B' . $fila->getRowIndex())->getValue();
                $nombre          = $hoja->getCell('C' . $fila->getRowIndex())->getValue();
                $apellidos       = $hoja->getCell('D' . $fila->getRowIndex())->getValue();
                $competencia     = $hoja->getCell('F' . $fila->getRowIndex())->getValue();
                $resultadoAp     = $hoja->getCell('G' . $fila->getRowIndex())->getFormattedValue();
                $juicioEv        = $hoja->getCell('H' . $fila->getRowIndex())->getFormattedValue();
                $fechaHoraJuicio = $hoja->getCell('J' . $fila->getRowIndex())->getValue();
                $funcionario     = $hoja->getCell('K' . $fila->getRowIndex())->getValue();

                if (!empty($tipoDoc) && !empty($numeroDoc)) {

                    $fechaEvaluacion = date('Y-m-d', strtotime($fechaHoraJuicio));

                    $dataBatch[] = [
                        'tipoIde'               => $tipoDoc,
                        'identificacion'        => $numeroDoc,
                        'nombre'                => $nombre,
                        'apellidos'             => $apellidos,
                        'estado'                => $estado,
                        'competencia'           => $competencia,
                        'rap'                   => $resultadoAp,
                        'evaluacion'            => $juicioEv,
                        'fechaEvaluacion'       => $fechaEvaluacion,
                        'responsableEvaluacion' => $funcionario,
                        'idUser'                => $idUser,
                        'idPrograma'            => $request->input('idPrograma'),
                    ];

                    if (count($dataBatch) >= $batchSize) {
                        DB::table('tmpRaps')->insert($dataBatch);
                        $dataBatch = [];
                    }
                } else {
                    break;
                }
            }

            if (!empty($dataBatch)) {
                DB::table('tmpRaps')->insert($dataBatch);
            }

            DB::table('archivosRap')->insert([
                'fechaReporte' => $fechaReporteFormateada,
                'idPrograma' => $request->input('idPrograma'),
                'idFicha' => $request->input('idFicha'),
                'idGrado' => $request->input('idGrado'),
                'idSede' => $request->input('idSede'),
                'created_at' => now(),
                'updated_at' => now(),
                'idUser' => $idUser ,
            ]);

            $password = bcrypt('123');
            DB::select('CALL RegistrarMatriculaAcademica(:p_idFicha, :p_idCompany, :p_password, :p_grado, :p_idSede, :p_idUser , :p_idPrograma)', [
                'p_idFicha'    => $request->input('idFicha'),
                'p_idCompany'  => $idCompany,
                'p_password'   => $password,
                'p_grado'      => $request->input('idGrado'),
                'p_idSede'     => $request->input('idSede'),
                'p_idUser'     => $idUser,
                'p_idPrograma' => $request->input('idPrograma'),
            ]);

            DB::commit();
            return response()->json(['mensaje' => 'Datos importados y procedimiento ejecutado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ocurrió un error al procesar el archivo o ejecutar el procedimiento',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function lastUpdateFicha($idFicha): JsonResponse
    {
        $data = ArchivoRap::where('idFicha', $idFicha)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
            
        return response()->json($data);
    }

}
