<?php

namespace App\Http\Controllers;

use App\Models\SeguimientoAspirante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SeguimientoAspiranteController extends Controller
{
    /** Plantilla oficial aprobada en Meta para la campaña de aspirantes. */
    private const PLANTILLA_OFICIAL = 'seguimiento_interes_programa_sena_v2';

    /** Idioma de la plantilla oficial. */
    private const PLANTILLA_IDIOMA = 'es';

    /**
     * Import applicants from an Excel (.xlsx) file.
     */
    public function importar(Request $request)
    {
        try {
            $archivo = $request->file('archivo');
            if (!$archivo || $archivo->getClientOriginalExtension() !== 'xlsx') {
                return response()->json(['error' => 'El archivo debe ser de formato .xlsx (Excel) únicamente'], 400);
            }

            $rutaArchivo = $archivo->getPathname();
            $spreadsheet = IOFactory::load($rutaArchivo);
            $hoja = $spreadsheet->getActiveSheet();
            $filas = $hoja->toArray();

            if (empty($filas) || count($filas) < 2) {
                return response()->json(['error' => 'El archivo no contiene datos válidos para importar'], 400);
            }

            $headerRow = $filas[0];
            $nombresIdx = -1;
            $apellidosIdx = -1;
            $celularIdx = -1;
            $correoIdx = -1;
            $centroIdx = -1;
            $programaIdx = -1;
            $fichaIdx = -1;
            $fechaIdx = -1;

            // Map columns case-insensitively
            foreach ($headerRow as $idx => $header) {
                if ($header === null) continue;
                $headerClean = mb_strtolower(trim($header), 'UTF-8');
                if ($headerClean === 'nombres' || $headerClean === 'nombre') {
                    $nombresIdx = $idx;
                } elseif ($headerClean === 'apellidos' || $headerClean === 'apellido') {
                    $apellidosIdx = $idx;
                } elseif ($headerClean === 'celular' || $headerClean === 'telefono' || $headerClean === 'teléfono' || $headerClean === 'móvil' || $headerClean === 'movil') {
                    $celularIdx = $idx;
                } elseif ($headerClean === 'correo' || $headerClean === 'email' || $headerClean === 'correo electrónico' || $headerClean === 'correo electronico') {
                    $correoIdx = $idx;
                } elseif ($headerClean === 'centro de formación' || $headerClean === 'centro de formacion' || $headerClean === 'centro formacion' || $headerClean === 'centro') {
                    $centroIdx = $idx;
                } elseif ($headerClean === 'programa' || $headerClean === 'programa de formación' || $headerClean === 'programa de formacion') {
                    $programaIdx = $idx;
                } elseif ($headerClean === 'ficha') {
                    $fichaIdx = $idx;
                } elseif ($headerClean === 'fecha') {
                    $fechaIdx = $idx;
                }
            }

            // Validate that critical column headers are present
            if ($celularIdx === -1 || $programaIdx === -1 || $centroIdx === -1 || $fichaIdx === -1) {
                return response()->json([
                    'error' => 'El archivo Excel debe contener las columnas obligatorias correspondientes a Celular, Programa, Centro de formación y Ficha.'
                ], 400);
            }

            $errors = [];
            $importedCount = 0;
            $erroredCount = 0;

            foreach ($filas as $index => $fila) {
                if ($index === 0) {
                    continue; // Skip header row
                }

                // Check if row is entirely empty
                $nonEmptyCells = array_filter($fila, function ($val) {
                    return $val !== null && trim($val) !== '';
                });
                if (empty($nonEmptyCells)) {
                    continue; // Skip blank rows
                }

                $rowNum = $index + 1;
                $rowErrors = [];

                $nombre = $nombresIdx !== -1 ? trim($fila[$nombresIdx] ?? '') : '';
                $apellido = $apellidosIdx !== -1 ? trim($fila[$apellidosIdx] ?? '') : '';
                $celular = $celularIdx !== -1 ? trim($fila[$celularIdx] ?? '') : '';
                $correo = $correoIdx !== -1 ? trim($fila[$correoIdx] ?? '') : '';
                $centro = $centroIdx !== -1 ? trim($fila[$centroIdx] ?? '') : '';
                $programa = $programaIdx !== -1 ? trim($fila[$programaIdx] ?? '') : '';
                $ficha = $fichaIdx !== -1 ? trim($fila[$fichaIdx] ?? '') : '';
                $fechaVal = $fechaIdx !== -1 ? $fila[$fechaIdx] : null;

                // Validate row fields
                if (empty($celular)) {
                    $rowErrors[] = 'El campo celular es obligatorio.';
                }
                if (empty($programa)) {
                    $rowErrors[] = 'El campo programa es obligatorio.';
                }
                if (empty($centro)) {
                    $rowErrors[] = 'El campo centro de formación es obligatorio.';
                }
                if (empty($ficha)) {
                    $rowErrors[] = 'El campo ficha es obligatorio.';
                }

                if (!empty($rowErrors)) {
                    $erroredCount++;
                    $errors[] = [
                        'fila' => $rowNum,
                        'aspirante' => trim($nombre . ' ' . $apellido) ?: 'Fila ' . $rowNum,
                        'detalles' => $rowErrors
                    ];
                    continue;
                }

                // Parse Excel date safely
                $fecha = $this->parseExcelDate($fechaVal);

                try {
                    SeguimientoAspirante::create([
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'celular' => $celular,
                        'correo' => $correo ?: null,
                        'centro_formacion' => $centro,
                        'programa' => $programa,
                        'ficha' => $ficha,
                        'fecha_registro_excel' => $fecha,
                        'estado' => 'Pendiente',
                        'respuesta' => null,
                        'ultimo_envio' => null,
                        'cantidad_envios' => 0
                    ]);
                    $importedCount++;
                } catch (\Exception $e) {
                    Log::error('Error insertando aspirante en fila ' . $rowNum . ': ' . $e->getMessage());
                    $erroredCount++;
                    $errors[] = [
                        'fila' => $rowNum,
                        'aspirante' => trim($nombre . ' ' . $apellido) ?: 'Fila ' . $rowNum,
                        'detalles' => ['Error en base de datos al insertar el registro.']
                    ];
                }
            }

            return response()->json([
                'message' => 'Proceso de importación finalizado',
                'imported' => $importedCount,
                'errored' => $erroredCount,
                'errors' => $errors
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error general de importación: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al procesar el archivo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get list of applicants with dynamic filters and pagination.
     */
    public function index(Request $request)
    {
        try {
            $query = SeguimientoAspirante::query();

            // Filter by Programa
            if ($request->filled('programa')) {
                $query->where('programa', $request->programa);
            }

            // Filter by Centro
            if ($request->filled('centro_formacion')) {
                $query->where('centro_formacion', $request->centro_formacion);
            }

            // Filter by Ficha
            if ($request->filled('ficha')) {
                $query->where('ficha', $request->ficha);
            }

            // Filter by Estado
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Filter by Name / Apellido (search term)
            if ($request->filled('nombre')) {
                $nombreSearch = $request->nombre;
                $query->where(function ($q) use ($nombreSearch) {
                    $q->where('nombre', 'LIKE', '%' . $nombreSearch . '%')
                      ->orWhere('apellido', 'LIKE', '%' . $nombreSearch . '%');
                });
            }

            // Filter by Celular
            if ($request->filled('celular')) {
                $query->where('celular', 'LIKE', '%' . $request->celular . '%');
            }

            // Filter by Date range
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha_registro_excel', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha_registro_excel', '<=', $request->fecha_hasta);
            }

            // Sort and Paginate
            $perPage = $request->get('per_page', 15);
            $aspirantes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json($aspirantes, 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener listado de aspirantes: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener la información: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get unique programs present in database.
     */
    public function getProgramas()
    {
        try {
            $programas = SeguimientoAspirante::select('programa')
                ->distinct()
                ->whereNotNull('programa')
                ->where('programa', '!=', '')
                ->orderBy('programa', 'asc')
                ->pluck('programa');

            return response()->json($programas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unique centers present in database.
     */
    public function getCentros()
    {
        try {
            $centros = SeguimientoAspirante::select('centro_formacion')
                ->distinct()
                ->whereNotNull('centro_formacion')
                ->where('centro_formacion', '!=', '')
                ->orderBy('centro_formacion', 'asc')
                ->pluck('centro_formacion');

            return response()->json($centros, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unique fichas present in database.
     */
    public function getFichas()
    {
        try {
            $fichas = SeguimientoAspirante::select('ficha')
                ->distinct()
                ->whereNotNull('ficha')
                ->where('ficha', '!=', '')
                ->orderBy('ficha', 'asc')
                ->pluck('ficha');

            return response()->json($fichas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete selected records.
     */
    public function eliminarSeleccionados(Request $request)
    {
        try {
            $ids = $request->get('ids');
            if (empty($ids) || !is_array($ids)) {
                return response()->json(['error' => 'Debe proporcionar una lista de identificadores válida.'], 400);
            }

            SeguimientoAspirante::whereIn('id', $ids)->delete();

            return response()->json(['message' => 'Registros seleccionados eliminados correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar los registros: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete all imported records.
     */
    public function eliminarTodos()
    {
        try {
            SeguimientoAspirante::truncate();
            return response()->json(['message' => 'Toda la información importada ha sido eliminada.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar todos los registros: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send WhatsApp campaign to selected applicants.
     *
     * Envía directamente a la API de Meta (WhatsApp Cloud API) usando la
     * configuración local (tabla telecom_configs). Módulo 100% autónomo.
     *
     * Parámetros:
     *  - ids        (array)  Identificadores de aspirantes destino.
     *  - mensaje    (string) Cuerpo del mensaje de texto (o valores de plantilla).
     *  - plantilla  (string) Opcional. Si se envía, se usa como plantilla aprobada de Meta.
     *  - idioma     (string) Opcional. Código de idioma de la plantilla (por defecto 'es').
     *  - parametros (array)  Opcional. Variables {{1}},{{2}}... de la plantilla.
     */
    public function enviarWhatsApp(Request $request)
    {
        try {
            $ids = $request->get('ids');

            if (empty($ids) || !is_array($ids)) {
                return response()->json(['error' => 'Debe proporcionar una lista de identificadores válida.'], 400);
            }

            $service = new \App\Services\Telecom\MetaService();

            if (!$service->estaConfigurado()) {
                return response()->json([
                    'error' => 'No hay una configuración activa de WhatsApp Cloud API en este backend. Configúrela en el módulo de configuración.'
                ], 422);
            }

            $aspirantes = SeguimientoAspirante::whereIn('id', $ids)->get();

            $enviados = 0;
            $fallidos = 0;
            $errores  = [];

            foreach ($aspirantes as $aspirante) {
                if (empty($aspirante->celular)) {
                    $fallidos++;
                    $errores[] = ['id' => $aspirante->id, 'error' => 'El aspirante no tiene celular registrado.'];
                    continue;
                }

                // Plantilla OFICIAL única, con EXACTAMENTE 4 variables en orden:
                // 1 Nombre, 2 Programa, 3 Ficha, 4 Centro.
                $variables = [
                    trim((string) $aspirante->nombre),
                    trim((string) $aspirante->programa),
                    trim((string) $aspirante->ficha),
                    trim((string) $aspirante->centro_formacion),
                ];

                // Validar que existan los 4 parámetros antes de enviar.
                if (count(array_filter($variables, fn ($v) => $v !== '')) !== 4) {
                    $fallidos++;
                    $errores[] = ['id' => $aspirante->id, 'error' => 'Faltan datos (nombre, programa, ficha o centro) para la plantilla.'];
                    continue;
                }

                $resultado = $service->enviarPlantilla(
                    $aspirante->celular,
                    self::PLANTILLA_OFICIAL,
                    self::PLANTILLA_IDIOMA,
                    $variables
                );

                if ($resultado['ok']) {
                    $enviados++;
                    $aspirante->update([
                        'estado'          => 'Enviado',
                        'waMessageId'     => $resultado['id'] ?? null,
                        'estadoEnvio'     => 'sent',
                        'errorEnvio'      => null,
                        'ultimo_envio'    => Carbon::now(),
                        'cantidad_envios' => DB::raw('cantidad_envios + 1'),
                    ]);
                } else {
                    $fallidos++;
                    $errores[] = ['id' => $aspirante->id, 'error' => $resultado['error'] ?? 'Error desconocido'];
                }
            }

            return response()->json([
                'message'  => "Campaña procesada. Enviados: {$enviados}, Fallidos: {$fallidos}.",
                'enviados' => $enviados,
                'fallidos' => $fallidos,
                'errores'  => $errores,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al procesar el envío de WhatsApp: ' . $e->getMessage());
            return response()->json(['error' => 'Error al procesar el envío de WhatsApp: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to safely parse dates from Excel files.
     */
    private function parseExcelDate($value)
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Handle numeric Excel date serial values
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                // Fail silently and fall back
            }
        }

        // Handle string date values
        if (is_string($value)) {
            $value = trim($value);
            
            // Try matching common formatting layouts
            $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $value)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Try next format
                }
            }

            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                // Return null if all parsing attempts fail
            }
        }

        return null;
    }
}
