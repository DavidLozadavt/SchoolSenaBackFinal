<?php

namespace App\Http\Controllers;

use App\Models\DetalleRmi;
use App\Models\Portafolio;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PortafolioController extends Controller
{
    public function index()
    {
        $portafolios = Portafolio::with('portafolioFichas.portafolioDocumentos')->get();

        return response()->json([
            'success' => true,
            'data' => $portafolios,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'required|string',
            'idContrato' => 'required|exists:contrato,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // ── 1. Cargar todo lo necesario en UNA sola query ──────────────────────
        $horarios = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
            'ficha.jornada',
            'gradoMateria.materia.padre',
            'contrato.persona',
        ])
            ->where('idContrato', $request->idContrato)
            ->where('estado', '!=', 'PENDIENTE')
            ->get();

        if ($horarios->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No hay horarios activos para este contrato.'], 422);
        }

        // ── 2. Precargar DetalleRmi y horarios compartidos ─────────────────────
        $idGradoMaterias = $horarios->pluck('idGradoMateria')->unique();

        $detallesRmiPorGM = DetalleRmi::whereHas(
            'horarioMateria',
            fn($q) =>
            $q->whereIn('idGradoMateria', $idGradoMaterias)
        )->get()->groupBy(fn($d) => $d->horarioMateria->idGradoMateria);

        // Horarios compartidos: misma ficha+gradoMateria, distinto contrato
        $idFichas = $horarios->pluck('idFicha')->unique();
        $compartidosPor = \App\Models\HorarioMateria::with('contrato.persona')
            ->whereIn('idFicha', $idFichas)
            ->whereIn('idGradoMateria', $idGradoMaterias)
            ->whereNotNull('idContrato')
            ->where('idContrato', '!=', $request->idContrato)
            ->get()
            ->groupBy(fn($h) => $h->idFicha . '-' . $h->idGradoMateria);

        // ── 3. Rango del año en curso (enero → fin del mes actual) ─────────────
        $inicioAno = now()->startOfYear();
        $finMesActual = now()->endOfMonth();

        // ── 4. Construir fichas ────────────────────────────────────────────────
        $fichas = $horarios->groupBy('idFicha')->map(function ($grupo) use ($detallesRmiPorGM, $compartidosPor, $inicioAno, $finMesActual) {
            $ficha = $grupo->first()->ficha;
            $programa = $ficha?->asignacion?->programa;

            $horariosConDatos = $grupo->map(function ($h) use ($detallesRmiPorGM, $compartidosPor, $inicioAno, $finMesActual) {
                $rap = $h->gradoMateria?->materia;
                $competencia = $rap?->padre;

                $duracionSesion = round(
                    (strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600,
                    2
                );

                $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicioAno);
                $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($finMesActual);

                $cantidadSesiones = $this->contarSesiones($desde, $hasta, (int) $h->idDia);

                $claveCompartido = $h->idFicha . '-' . $h->idGradoMateria;
                $compartidoCon = ($compartidosPor[$claveCompartido] ?? collect())
                    ->map(
                        fn($otro) => $otro->contrato?->persona
                        ? trim($otro->contrato->persona->nombre1 . ' ' . $otro->contrato->persona->apellido1)
                        : null
                    )
                    ->filter()->unique()->values()->toArray();

                return [
                    'idGradoMateria' => $h->idGradoMateria,
                    'competencia' => $competencia?->nombreMateria,
                    'resultadoAprendizaje' => $rap?->nombreMateria,
                    'idHorario' => $h->id,
                    'horaInicial' => $h->horaInicial,
                    'horaFinal' => $h->horaFinal,
                    'fechaInicial' => $h->fechaInicial,
                    'fechaFinal' => $h->fechaFinal,
                    'duracionSesion' => $duracionSesion,
                    'cantidadSesiones' => $cantidadSesiones,
                    'duracionHoras' => round($duracionSesion * $cantidadSesiones, 2),
                    'idDia' => $h->idDia,
                    'compartidoCon' => $compartidoCon,
                ];
            });

            $resultados = $horariosConDatos->groupBy('idGradoMateria')->map(function ($gm) use ($detallesRmiPorGM) {
                $primero = $gm->first();
                $detallesRmi = $detallesRmiPorGM[$primero['idGradoMateria']] ?? collect();

                return [
                    'idGradoMateria' => $primero['idGradoMateria'],
                    'competencia' => $primero['competencia'],
                    'resultadoAprendizaje' => $primero['resultadoAprendizaje'],
                    'estadoAsociacion' => $detallesRmi->first()?->estadoAsociacion,
                    'compartidoCon' => $primero['compartidoCon'],
                    'horarios' => $gm->map(fn($item) => [
                        'idHorario' => $item['idHorario'],
                        'horaInicial' => $item['horaInicial'],
                        'horaFinal' => $item['horaFinal'],
                        'fechaInicial' => $item['fechaInicial'],
                        'fechaFinal' => $item['fechaFinal'],
                        'duracionSesion' => $item['duracionSesion'],
                        'cantidadSesiones' => $item['cantidadSesiones'],
                        'duracionHoras' => $item['duracionHoras'],
                        'idDia' => $item['idDia'],
                    ])->values(),
                ];
            })->values();

            return [
                'idFicha' => $ficha?->id,
                'fichaUrlDocumento' => $ficha?->documento,
                'codigoFicha' => $ficha?->codigo,
                'jornada' => $ficha?->jornada?->nombreJornada ?? '',
                'programaFormacion' => $programa?->nombrePrograma,
                'programaUrlDocumento' => $programa?->documento,
                'codigoPrograma' => $programa?->codigoPrograma,
                'resultados' => $resultados,
            ];
        })->values();

        // ── 5. Persistir todo en una transacción ──────────────────────────────
        $slugsBase = [
            'programa-formacion',
            'proyecto-formativo',
            'planeacion-pedagogica',
            'horario-ficha',
            'actas-equipo-ejecutor',
            'guias-aprendizaje',
            'instrumentos-evaluacion',
            'materia-formacion',
            'juicios-evaluativos',
        ];

        $categoriasBase = \App\Models\PortafolioCategoria::whereIn('slug', $slugsBase)
            ->where(function ($q) use ($request) {
                $q->whereNull('idContrato')
                  ->orWhere('idContrato', $request->idContrato);
            })
            ->orderBy('orden')
            ->get(); // trae id, nombre, slug — lo necesitas para el insert

        $persona = $horarios->first()?->contrato?->persona;
        $instructorLider = $persona ? trim($persona->nombre1 . ' ' . $persona->apellido1) : '';

        \DB::transaction(function () use ($request, $fichas, $categoriasBase, $instructorLider, &$portafolio) {
            $portafolio = Portafolio::create($request->only('descripcion', 'idContrato'));

            $excelService = new \App\Services\PlaneacionExcelService();

            // ── Se consulta UNA sola vez, fuera del foreach ─────────────────────
            $categoriasConHijos = \App\Models\PortafolioCategoria::whereIn('slug', ['horario-ficha', 'actas-equipo-ejecutor'])
                ->where(function ($q) use ($request) {
                    $q->whereNull('idContrato')
                      ->orWhere('idContrato', $request->idContrato);
                })
                ->with(['hijos' => function ($q) use ($request) {
                    $q->whereNull('idContrato')
                      ->orWhere('idContrato', $request->idContrato);
                }])
                ->get();

            foreach ($fichas as $ficha) {
                $pf = \App\Models\PortafolioFicha::create([
                    'idPortafolio' => $portafolio->id,
                    'idFicha' => $ficha['idFicha'],
                    'descripcion' => "Ficha " . $ficha['codigoFicha'],
                ]);

                // Generar Excel de planeación
                $urlPlaneacion = '';
                try {
                    $urlPlaneacion = $excelService->generarExcelPlaneacion(
                        $ficha['idFicha'],
                        $ficha['codigoFicha'],
                        $instructorLider,
                        $ficha['jornada'] ?? '',
                        $ficha['programaFormacion'] ?? ''
                    );
                } catch (\Exception $e) {
                    \Log::error('Error generando excel planeación: ' . $e->getMessage());
                }

                // Categorías que se documentan a través de sus hijos (trimestres) — no deben tener fila propia
                $slugsConHijos = ['horario-ficha', 'actas-equipo-ejecutor'];

                \App\Models\PortafolioDocumento::insert(
                    $categoriasBase
                        ->reject(fn($cat) => in_array($cat->slug, $slugsConHijos))
                        ->map(fn($cat) => [
                            'idPortafolioFichas' => $pf->id,
                            'idCategoria' => $cat->id,
                            'descripcion' => $cat->nombre,
                            'urlDocumento' => match ($cat->slug) {
                                'programa-formacion' => $ficha['programaUrlDocumento'] ?? '',
                                'proyecto-formativo' => $ficha['fichaUrlDocumento'] ?? '',
                                'planeacion-pedagogica' => $urlPlaneacion ?? '',
                                default => null, // no crear si no hay documento
                            },
                        ])
                        ->filter(fn($doc) => $doc['urlDocumento'] !== null && $doc['urlDocumento'] !== '')
                        ->values()
                        ->toArray()
                );

                // ── 6. Asociar actas y horarios mensuales a los trimestres ───────────────────
                $actasPorTrimestre = [
                    't1' => [],
                    't2' => [],
                    't3' => [],
                    't4' => []
                ];

                // Obtener actas de la ficha
                $actas = \App\Models\Acta::where('idFicha', $ficha['idFicha'])
                    ->whereYear('fecha', now()->year)
                    ->get();

                foreach ($actas as $acta) {
                    $mesActa = \Carbon\Carbon::parse($acta->fecha)->month;
                    $trimestre = ceil($mesActa / 3); // 1-3: T1, 4-6: T2, 7-9: T3, 10-12: T4
                    $actasPorTrimestre["t$trimestre"][] = $acta;
                }

                // Asociar actas a sus respectivas categorías
                foreach ($actasPorTrimestre as $trimestre => $actasTrimestre) {
                    \Log::info('Debug actas', [
                        'idFicha' => $ficha['idFicha'],
                        'totalActas' => $actas->count(),
                        'actasPorTrimestre' => array_map('count', $actasPorTrimestre),
                    ]);

                    if (empty($actasTrimestre))
                        continue;

                    $categoriaActas = \App\Models\PortafolioCategoria::where('slug', "actas-equipo-ejecutor-$trimestre")
                        ->where(function ($q) use ($request) {
                            $q->whereNull('idContrato')
                              ->orWhere('idContrato', $request->idContrato);
                        })
                        ->first();
                    if ($categoriaActas) {
                        foreach ($actasTrimestre as $acta) {
                            $urlDocumento = $acta->rutaDocumentoUrl ?: '';

                            if ($urlDocumento === '') {
                                \Log::warning("Acta {$acta->id} sin documento, omitiendo registro en el portafolio.", [
                                    'idFicha' => $ficha['idFicha'],
                                    'idActa' => $acta->id,
                                ]);
                                continue;
                            }

                            \App\Models\PortafolioDocumento::create([
                                'idPortafolioFichas' => $pf->id,
                                'idCategoria' => $categoriaActas->id,
                                'descripcion' => "Acta No. {$acta->id} - {$acta->nombre}",
                                'urlDocumento' => $urlDocumento,
                            ]);
                        }
                    }
                }

                // Generar Horario por mes (para todos los meses que tienen programación)
                $mesesHorarios = \App\Models\HorarioMateria::where('idFicha', $ficha['idFicha'])
                    ->where('estado', '!=', 'PENDIENTE')
                    ->where(function ($q) {
                        $q->whereYear('fechaInicial', now()->year)
                            ->orWhereYear('fechaFinal', now()->year);
                    })
                    ->get()
                    ->flatMap(function ($h) {
                        $inicio = \Carbon\Carbon::parse($h->fechaInicial)->startOfMonth();
                        $fin = \Carbon\Carbon::parse($h->fechaFinal)->endOfMonth();
                        $meses = [];
                        $cursor = $inicio->copy();
                        while ($cursor->lte($fin)) {
                            if ($cursor->year === now()->year) {
                                $meses[] = $cursor->month;
                            }
                            $cursor->addMonth();
                        }
                        return $meses;
                    })->unique()->sort()->values();

                foreach ($mesesHorarios as $mes) {
                    $trimestreNum = ceil($mes / 3);
                    $trimestreSlug = "t$trimestreNum";
                    $categoriaHorario = \App\Models\PortafolioCategoria::where('slug', "horario-ficha-$trimestreSlug")
                        ->where(function ($q) use ($request) {
                            $q->whereNull('idContrato')
                              ->orWhere('idContrato', $request->idContrato);
                        })
                        ->first();

                    if ($categoriaHorario) {
                        $urlHorarioMensual = $this->generarPdfHorarioMensual($ficha['idFicha'], $mes);
                        $nombreMes = ucfirst(\Carbon\Carbon::create(null, $mes, 1)->translatedFormat('F'));

                        \App\Models\PortafolioDocumento::create([
                            'idPortafolioFichas' => $pf->id,
                            'idCategoria' => $categoriaHorario->id,
                            'descripcion' => "Horario mensual - $nombreMes",
                            'urlDocumento' => $urlHorarioMensual,
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Portafolio creado correctamente.',
            'data' => $portafolio->load('portafolioFichas.ficha', 'portafolioFichas.portafolioDocumentos.categoria'),
        ], 201);
    }

    // ── Helper privado ─────────────────────────────────────────────────────────
    private function contarSesiones(\Carbon\Carbon $desde, \Carbon\Carbon $hasta, int $idDia): int
    {
        if ($desde->gt($hasta))
            return 0;

        // idDia: 1=Lunes…6=Sábado, 7=Domingo → Carbon: 1=Lunes…6=Sábado, 0=Domingo
        $diaSemanaCarbon = $idDia === 7 ? 0 : $idDia;

        // Contar con aritmética en lugar de iterar día a día
        $totalDias = $desde->diffInDays($hasta) + 1;
        $semanasCompletas = intdiv($totalDias, 7);
        $diasExtra = $totalDias % 7;

        $count = $semanasCompletas;
        for ($i = 0; $i < $diasExtra; $i++) {
            if ($desde->copy()->addDays($i)->dayOfWeek === $diaSemanaCarbon) {
                $count++;
            }
        }

        return $count;
    }

    public function show($id)
    {
        $portafolio = Portafolio::with([
            'contrato',
            'portafolioFichas.ficha',
            'portafolioFichas.portafolioDocumentos',
        ])->find($id);

        if (!$portafolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portafolio no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $portafolio,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'sometimes|string',
            'idContrato' => 'sometimes|exists:contrato,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $portafolio = Portafolio::find($id);

        if (!$portafolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portafolio no encontrado.',
            ], 404);
        }

        $portafolio->update($request->only('descripcion', 'idContrato'));

        return response()->json([
            'success' => true,
            'message' => 'Portafolio actualizado correctamente.',
            'data' => $portafolio->load('contrato'),
        ]);
    }

    public function destroy($id)
    {
        $portafolio = Portafolio::with('portafolioFichas.portafolioDocumentos')->find($id);

        if (!$portafolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portafolio no encontrado.',
            ], 404);
        }

        \DB::transaction(function () use ($portafolio) {
            foreach ($portafolio->portafolioFichas as $portafolioFicha) {
                foreach ($portafolioFicha->portafolioDocumentos as $documento) {
                    $this->eliminarArchivoSiEsPropio($documento->urlDocumento);
                }

                // Elimina todos los documentos de esta ficha
                $portafolioFicha->portafolioDocumentos()->delete();
            }

            // Elimina todas las fichas del portafolio
            $portafolio->portafolioFichas()->delete();

            // Finalmente elimina el portafolio
            $portafolio->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Portafolio eliminado correctamente.',
        ]);
    }

    /**
     * Borra el archivo físico solo si pertenece a la carpeta portafolio-documentos.
     */
    private function eliminarArchivoSiEsPropio(?string $urlDocumento): void
    {
        if (empty($urlDocumento)) {
            return;
        }

        if (!str_contains($urlDocumento, 'portafolio-documentos')) {
            return;
        }

        // Normaliza la ruta: quita "/storage/" o "storage/" al inicio
        $relativePath = preg_replace('#^/?storage/#', '', $urlDocumento);

        if (\Storage::disk('public')->exists($relativePath)) {
            \Storage::disk('public')->delete($relativePath);
        }
    }

    public function fichas($id)
    {
        $portafolio = Portafolio::with([
            'portafolioFichas.ficha',
            'portafolioFichas.portafolioDocumentos',
        ])->find($id);

        if (!$portafolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portafolio no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $portafolio->portafolioFichas,
        ]);
    }
    /**
     * Portafolios de un instructor específico.
     */
    public function portafoliosInstructor($idContrato)
    {
        $portafolios = Portafolio::with('portafolioFichas.portafolioDocumentos')
            ->where('idContrato', '=', $idContrato)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $portafolios,
        ]);
    }

    /**
     * Descargar el portafolio en un archivo .zip
     * con una carpeta por ficha y sus respectivos documentos adentro.
     */
    public function downloadZip($id)
    {
        $portafolio = Portafolio::with([
            'portafolioFichas.ficha',
            'portafolioFichas.portafolioDocumentos.categoria.padre.padre.padre.padre', // <-- eager load de múltiples niveles
        ])->find($id);

        if (!$portafolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portafolio no encontrado.',
            ], 404);
        }

        $zip = new \ZipArchive();

        $zipFileName = str_replace(' ', '_', $portafolio->descripcion) . '.zip';
        $zipFilePath = storage_path('app/public/' . $zipFileName);

        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP.',
            ], 500);
        }

        $portafolioFolder = $this->sanitizeName($portafolio->descripcion, 'Portafolio_' . $portafolio->id);

        foreach ($portafolio->portafolioFichas as $portafolioFicha) {

            $fichaFolder = $portafolioFolder . '/' . $this->sanitizeName($portafolioFicha->descripcion, 'Ficha_' . $portafolioFicha->id);

            foreach ($portafolioFicha->portafolioDocumentos as $documento) {

                // ── Construye la ruta de carpetas según la jerarquía de categorías ──
                $categoriaFolder = $documento->categoria
                    ? $fichaFolder . '/' . $this->buildCategoryPath($documento->categoria)
                    : $fichaFolder . '/Otros_Documentos';

                // Crear los directorios nivel por nivel para asegurar compatibilidad al extraer
                $pathSegments = explode('/', $categoriaFolder);
                $currentPath = '';
                foreach ($pathSegments as $segment) {
                    $currentPath .= ($currentPath === '' ? '' : '/') . $segment;
                    $zip->addEmptyDir($currentPath);
                }

                if (empty($documento->urlDocumento)) {
                    \Log::warning("DOC {$documento->id} [{$documento->descripcion}]: URL vacía, se omite.");
                    continue;
                }

                // Usamos resolveStoragePath para extraer correctamente el path físico
                $filePath = $this->resolveStoragePath($documento->urlDocumento);

                if ($filePath && file_exists($filePath) && is_file($filePath)) {
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    $nombreArchivo = $this->sanitizeName($documento->descripcion, 'Documento_' . $documento->id) . '.' . $extension;

                    $zip->addFile($filePath, $categoriaFolder . '/' . $nombreArchivo);

                }
            }
        }

        $zip->close();

        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }

    /**
     * Construye la ruta de carpetas subiendo por la jerarquía de categorías
     * (padre/hijo/nieto...) hasta llegar a la raíz.
     * Ej: "Actas de Equipo Ejecutor/Primer Trimestre"
     */
    private function buildCategoryPath($categoria, int $maxDepth = 10): string
    {
        $segmentos = [];
        $actual = $categoria;
        $vistos = [];

        while ($actual && $maxDepth > 0) {
            // Guard contra ciclos accidentales en la tabla self-referencing
            if (in_array($actual->id, $vistos)) {
                break;
            }
            $vistos[] = $actual->id;

            array_unshift($segmentos, $this->sanitizeName($actual->nombre, 'Categoria_' . $actual->id));

            $actual = $actual->padre; // sube un nivel
            $maxDepth--;
        }

        return implode('/', $segmentos);
    }

    private function sanitizeName(?string $name, string $fallback): string
    {
        if (empty(trim($name ?? ''))) {
            return $fallback;
        }

        // Elimina caracteres inválidos en nombres de carpeta/archivo
        $sanitized = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        $sanitized = trim($sanitized);

        return str_replace(' ', '_', $sanitized);
    }
    private function resolveStoragePath(string $url): ?string
    {
        // Quita el dominio si viene como URL absoluta
        $path = preg_replace('#^https?://[^/]+#', '', $url);

        // Quita slash inicial
        $path = ltrim($path, '/');

        // Normaliza: quita el prefijo "storage/" una sola vez
        $path = preg_replace('#^storage/#', '', $path);

        // También soporta paths que ya vienen como rutas relativas puras
        // (sin "storage/" al inicio, ej: "programas/documentos/...")
        return storage_path('app/public/' . $path);
    }

    protected function generarPdfHorarioMensual($idFicha, $mes)
    {
        $ficha = \App\Models\Ficha::findOrFail($idFicha);
        $inicioMes = \Carbon\Carbon::create(now()->year, $mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $horarios = \App\Models\HorarioMateria::with(['contrato.persona', 'gradoMateria.materia.padre'])
            ->where('idFicha', $idFicha)
            ->where('estado', '!=', 'PENDIENTE')
            ->where(function ($q) use ($inicioMes, $finMes) {
                $q->whereBetween('fechaInicial', [$inicioMes->toDateString(), $finMes->toDateString()])
                    ->orWhereBetween('fechaFinal', [$inicioMes->toDateString(), $finMes->toDateString()])
                    ->orWhere(function ($q2) use ($inicioMes, $finMes) {
                        $q2->where('fechaInicial', '<=', $inicioMes->toDateString())
                            ->where('fechaFinal', '>=', $finMes->toDateString());
                    });
            })
            ->get();

        $instructoresConColor = $this->obtenerInstructoresConColor($horarios);
        $calendario = $this->generarCalendarioMensual($horarios, $inicioMes->toDateString(), $finMes->toDateString());

        $trimestre = ceil($mes / 3);
        $pdf = Pdf::loadView('pdf.horario_mensual', [
            'ficha' => $ficha,
            'calendario' => $calendario,
            'instructoresConColor' => $instructoresConColor,
            'trimestre' => $trimestre,
        ])->setPaper('letter');

        if (!\Storage::disk('public')->exists('portafolios/horarios')) {
            \Storage::disk('public')->makeDirectory('portafolios/horarios');
        }

        $nombreMes = ucfirst($inicioMes->translatedFormat('F'));
        $path = "portafolios/horarios/{$ficha->codigo}-$nombreMes.pdf";
        $pdf->save(storage_path("app/public/$path"));
        return url("storage/$path");
    }
    protected function obtenerInstructoresConColor($horarios)
    {
        $instructores = $horarios->groupBy('idContrato')->map(function ($grupo) {
            $persona = $grupo->first()->contrato?->persona;
            return [
                'idContrato' => $grupo->first()->idContrato,
                'nombre' => $persona?->nombre1,
                'apellido' => $persona?->apellido1,
            ];
        })->values();

        $colores = ['#FFD700', '#FFA500', '#4CAF50', '#2196F3', '#E91E63', '#9C27B0'];
        return $instructores->map(function ($instructor, $index) use ($colores) {
            $instructor['color'] = $colores[$index % count($colores)];
            return $instructor;
        });
    }

    protected function generarCalendarioMensual($horarios, $inicio, $fin)
    {
        $diasDelMes = \Carbon\Carbon::parse($inicio)->toPeriod(\Carbon\Carbon::parse($fin));
        $calendario = [];

        foreach ($diasDelMes as $dia) {
            $mesKey = $dia->format('Y-m');
            if (!isset($calendario[$mesKey])) {
                $calendario[$mesKey] = [];
            }
            $calendario[$mesKey][$dia->day] = ['colores' => [], 'esFinde' => $dia->isWeekend()];
        }

        foreach ($horarios->groupBy('idContrato') as $idContrato => $horariosInstructor) {
            $instructorConColor = $this->obtenerInstructoresConColor($horarios)->firstWhere('idContrato', $idContrato);
            if (!$instructorConColor)
                continue;
            $color = $instructorConColor['color'];

            foreach ($horariosInstructor as $h) {
                $desde = \Carbon\Carbon::parse($h->fechaInicial)->max(\Carbon\Carbon::parse($inicio));
                $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min(\Carbon\Carbon::parse($fin));
                $idDiaInt = (int) $h->idDia;
                $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;

                $cursor = $desde->copy();
                while ($cursor->lte($hasta)) {
                    if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                        $mesKey = $cursor->format('Y-m');
                        $day = $cursor->day;
                        if (isset($calendario[$mesKey][$day]) && !in_array($color, $calendario[$mesKey][$day]['colores'])) {
                            $calendario[$mesKey][$day]['colores'][] = $color;
                        }
                    }
                    $cursor->addDay();
                }
            }
        }

        return $calendario;
    }
}