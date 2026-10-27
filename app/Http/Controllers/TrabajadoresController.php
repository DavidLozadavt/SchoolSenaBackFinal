<?php

namespace App\Http\Controllers;

use App\Models\Trabajador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Util\KeyUtil;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Models\ActivationCompanyUser;
use Illuminate\Support\Facades\Log;
use DateTimeInterface;
use Throwable;

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

        $encabezados = array_values(array_filter(array_map(function ($encabezado) {
            return strtolower(trim((string) $encabezado));
        }, $filas[0])));
        $cantidadColumnas = count($encabezados);

        $trabajadores = [];

        foreach ($filas as $index => $fila) {
            if ($index === 0) {
                continue;
            }

            $fila = array_pad(array_slice($fila, 0, $cantidadColumnas), $cantidadColumnas, null);
            $registro = array_combine($encabezados, $fila);
            if ($registro === false) {
                continue;
            }

            $identificacion = $this->textoPlano($registro['identificacion'] ?? null);
            $identificacion = str_replace('.', '', $identificacion);
            if ($identificacion === '' || $this->textoPlano($registro['nombre1'] ?? null) === '' || $this->textoPlano($registro['apellido1'] ?? null) === '') {
                continue;
            }

            $fechaInicial = $this->fechaExcel($registro['fecha_inicial'] ?? null);
            if (!$fechaInicial) {
                return response()->json([
                    'error' => 'La fila de ' . $this->textoPlano($registro['nombre1']) . ' no tiene fecha inicial válida.'
                ], 422);
            }

            $fechaFinal = $this->fechaExcel($registro['fecha_final'] ?? null);
            if (!$fechaFinal) {
                $fechaFinal = '2099-12-31';
            }

            $valor = $registro['valor'] ?? null;
            if ($valor === null || $valor === '') {
                $valor = null;
            }

            $trabajadores[] = [
                'nombre1' => $this->textoPlano($registro['nombre1'] ?? null),
                'nombre2' => $this->textoPlano($registro['nombre2'] ?? null),
                'apellido1' => $this->textoPlano($registro['apellido1'] ?? null),
                'apellido2' => $this->textoPlano($registro['apellido2'] ?? null),
                'tipo_identificacion' => $this->textoPlano($registro['tipo_identificacion'] ?? null),
                'identificacion' => $identificacion,
                'correo' => $this->textoPlano($registro['correo'] ?? null),
                'celular' => $this->textoPlano($registro['celular'] ?? null) ?: null,
                'fecha_nacimiento' => $this->fechaExcel($registro['fecha_nacimiento'] ?? null),
                'tipo_contratacion' => $this->textoPlano($registro['tipo_contratacion'] ?? null),
                'valor' => $valor,
                'fecha_inicial' => $fechaInicial,
                'fecha_final' => $fechaFinal,
                'rol' => $this->textoPlano($registro['rol'] ?? null),
                'area_conocimientos' => $this->textoPlano($registro['area_conocimientos'] ?? null),
                'nivel_educativo' => $this->textoPlano($registro['nivel_educativo'] ?? null) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($trabajadores)) {
            return response()->json(['error' => 'El archivo no tiene trabajadores para cargar.'], 422);
        }

        Trabajador::insert($trabajadores);

        return response()->json(['message' => 'Archivo cargado. Se prepararon ' . count($trabajadores) . ' trabajadores.']);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
        }
    }

    private function textoPlano($valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_float($valor) || is_int($valor)) {
            return sprintf('%.0f', $valor);
        }
        $texto = trim((string) $valor);
        return mb_convert_encoding($texto, 'UTF-8', 'auto');
    }

    private function fechaExcel($valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $texto);
            if ($fecha instanceof \DateTime) {
                return $fecha->format('Y-m-d');
            }
        }

        $tiempo = strtotime($texto);
        return $tiempo ? date('Y-m-d', $tiempo) : null;
    }



   public function ejecutarProcedimiento()
{
    try {
        $user = auth()->user();
        $userId = $user->id;

        \Log::info('ID del usuario: ' . $userId);

        $activation = ActivationCompanyUser::byUser($userId)
            ->active()
            ->first();

        if (!$activation) {
            return response()->json([
                'error' => 'Usuario no tiene empresa asignada'
            ], 400);
        }

        $companyId = $activation->company_id;
        $centroId = $user->idCentroFormacion ?: null;

        \Log::info('Centro del usuario: ' . $centroId);

        $encryptedPassword = bcrypt('123');

        $nomRol = 'DOCENTEUP'; // Cambiar el rol dinamicamente para el docente y el trabajado administrativo PENDIENTE!!

        DB::statement('CALL cargarTrabajadores(?, ?, ?, ?)', [
            $companyId,
            $centroId,
            $nomRol,
            $encryptedPassword
        ]);

        return response()->json([
            'message' => 'Operación realizada con éxito!'
        ], 200);

    } catch (Throwable $e) {
        Log::error($e->getMessage());
        return response()->json([
            'error' => 'No se pudieron crear los trabajadores. Vuelve a subir el archivo.'
        ], 500);
    }
}

    
    
}   