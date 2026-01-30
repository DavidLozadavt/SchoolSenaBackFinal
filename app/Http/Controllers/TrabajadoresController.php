<?php

namespace App\Http\Controllers;

use App\Models\Trabajador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Util\KeyUtil;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TrabajadoresController extends Controller
{


    public function cargarDesdeCSV(Request $request)
{
    try {
        $archivo = $request->file('archivo');
        if (!$archivo || !in_array($archivo->getClientOriginalExtension(), ['xlsx', 'xls'])) {
            return response()->json(['error' => 'El archivo debe ser un archivo Excel válido'], 400);
        }

        $rutaArchivo = $archivo->getPathname();
        $spreadsheet = IOFactory::load($rutaArchivo);
        $hoja = $spreadsheet->getActiveSheet();

        $filas = $hoja->toArray();
        if (empty($filas) || count($filas) < 2) {
            return response()->json(['error' => 'El archivo no contiene datos válidos'], 400);
        }

        $encabezados = array_map('strtolower', $filas[0]);

        $trabajadores = [];

        foreach ($filas as $index => $fila) {
            if ($index === 0) {
                continue;
            }
        
            $registro = array_map(function ($valor) {
                return is_string($valor) ? mb_convert_encoding($valor, 'UTF-8', 'auto') : $valor;
            }, array_combine($encabezados, $fila));
        
            if (empty($registro['identificacion']) || empty($registro['nombre1']) || empty($registro['apellido1'])) {
                continue; 
            }
        
            $trabajadores[] = [
                'nombre1' => $registro['nombre1'] ?? '',
                'nombre2' => $registro['nombre2'] ?? '',
                'apellido1' => $registro['apellido1'] ?? '',
                'apellido2' => $registro['apellido2'] ?? '',
                'tipo_identificacion' => $registro['tipo_identificacion'] ?? '',
                'identificacion' => str_replace('.', '', $registro['identificacion']),
                'correo' => $registro['correo'] ?? '',
                'celular' => $registro['celular'] ?? null,
                'fecha_nacimiento' => $registro['fecha_nacimiento'] ?? null,
                'tipo_contratacion' => $registro['tipo_contratacion'] ?? '',
                'valor' => $registro['valor'] ?? null,
                'fecha_inicial' => $registro['fecha_inicial'] ?? null,
                'fecha_final' => $registro['fecha_final'] ?? null,
                'rol' => $registro['rol'] ??  null,
                'area_conocimientos' => $registro['area_conocimientos'] ?? '',
                'nivel_educativo' => $registro['nivel_educativo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        if (!empty($trabajadores)) {
            Trabajador::insert($trabajadores);
        }

        return response()->json(['message' => 'Carga masiva de estudiantes completada']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
        }
    }



    public function ejecutarProcedimiento()
    {
        try {
            $companyId = KeyUtil::idCompany();
            $encryptedPassword = bcrypt('123');

            DB::select('CALL `cargarTrabajadores`(:companyId, :encryptedPassword)', [
                'companyId' => $companyId,
                'encryptedPassword' => $encryptedPassword
            ]);
    
            return response()->json(['message' => 'Operación realizada con éxito!'], 200, [], JSON_UNESCAPED_UNICODE);
    
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    
}   