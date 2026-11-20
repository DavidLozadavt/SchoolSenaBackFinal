<?php

namespace App\Http\Controllers;

use App\Models\HorarioMateria;
use App\Models\MatriculaAcademica;
use App\Models\Matricula;
use App\Models\Ficha;
use App\Models\Contract;
use App\Models\Sede;
use App\Models\Company;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LyraController extends Controller
{
    /**
     * Consultas que Nexo sabe hacer y los permisos que habilitan cada una
     * (basta con uno). Es la única fuente de verdad: con ella se autoriza cada
     * petición y con ella se le dice a Nexo qué puede ofrecer.
     *
     * Antes se decidía por el NOMBRE del rol y por subcadena ("ADMIN" valía
     * para cualquier rol que lo contuviera). Dos usuarios con el mismo rol
     * pueden tener permisos distintos —una activación puede tener varios
     * roles— y un rol nuevo no tenía cómo entrar: ahora manda el permiso.
     *
     * null = dato propio (horario, notas, pendientes): basta la sesión y la
     * consulta sólo devuelve lo del propio usuario.
     */
    private const CAPACIDADES = [
        'clases' => null,
        'horario' => null,
        'notas' => null,
        'pendientes' => null,
        'periodos_propios' => null,
        'fichas' => ['GESTION_ACADEMICA', 'AULA_VIRTUAL_INSTRUCTOR_FORMACIONES', 'GESTION_ESTUDIANTES'],
        'estudiantes' => ['GESTION_ACADEMICA', 'AULA_VIRTUAL_INSTRUCTOR_FORMACIONES', 'GESTION_ESTUDIANTES'],
        'entregas' => ['GESTION_ACADEMICA', 'AULA_VIRTUAL_INSTRUCTOR_ACTIVIDADES', 'GESTION_NOTAS_ACADEMICAS', 'GESTION_EVALUACIONES'],
        'resumen' => ['GESTION_ACADEMICA', 'ADMINISTRATIVO_SENA', 'GESTION_REGIONAL', 'GESTION_CENTROS_FORMACION'],
        'nomina' => ['GESTION_NOMINA'],
        'instructores' => ['GESTION_INSTRUCTOR', 'ADMINISTRATIVO_SENA', 'GESTION_PROFESOR', 'CIADET_SECRETARIA_INSTRUCTORES'],
        'contratos' => ['GESTION_CONTRATOS', 'GESTION_CONTRATACION', 'GESTION_LABORAL'],
        // Igual que la pantalla /centrosFormacion (ProtectedRoute): sólo su permiso.
        'centros' => ['GESTION_CENTROS_FORMACION'],
        'sedes' => ['GESTION_SEDES', 'GESTION_SEDE', 'GESTION_CENTROS_FORMACION'],
        'ambientes' => ['GESTION_AMBIENTES', 'GESTION_CENTROS_FORMACION'],
        'periodos' => ['GESTION_PERIODOS'],
        'jornadas' => ['GESTION_JORNADAS'],
        'planeacion' => ['GESTION_PLANEACION', 'GESTION_ACADEMICA'],
        // Mismos permisos que exige la pantalla del front (ProtectedRoute).
        'regionales' => ['GESTION_REGIONAL'],
        'eventos' => ['GESTION_EVENTOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'crear_evento' => ['GESTION_EVENTOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'formularios' => ['GESTION_FORMULARIOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'carpetas' => ['CIADET_SECRETARIA_CARPETAS'],
        'configuracion_pagos' => ['GESTION_CONFIGURACION_PAGOS'],
        // Fase 6: mismos permisos que la pantalla correspondiente.
        'crear_formulario' => ['GESTION_FORMULARIOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'pagos_pendientes' => ['GESTION_PAGOS_CONTRATOS'],
        'solicitudes_inscripcion' => ['GESTION_ACADEMICA'],
        'configuracion_inscripcion' => ['GESTION_ACADEMICA', 'CONFIGURACION_DE_INSCRIPCION'],
        'multimedia' => ['GESTION_MULTIMEDIA', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'informes_instructores' => ['GESTION_INSTRUCTOR_PAGO'],
        // Fase 7: los mismos permisos que el middleware de PUT formularios y de
        // POST eventos-multimedia/{id} (la pantalla del constructor y la de eventos).
        'editar_formulario' => ['GESTION_FORMULARIOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'publicar_formulario' => ['GESTION_FORMULARIOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
        'vincular_formulario' => ['GESTION_EVENTOS', 'GESTION_USUARIO', 'AULA_VIRTUAL_INSTRUCTOR'],
    ];

    /** Tipos del constructor (enum `formulario_preguntas.tipo`) y los que llevan opciones. */
    private const TIPOS_PREGUNTA = ['texto_corto', 'texto_largo', 'opcion_multiple', 'casillas', 'desplegable',
                                    'escala_lineal', 'fecha', 'hora', 'archivo'];
    private const TIPOS_CON_OPCIONES = ['opcion_multiple', 'casillas', 'desplegable'];
    /** Claves de `configuracion` que el constructor del front entiende. */
    private const CONFIG_PERMITIDA = ['soloNumeros', 'valorUnico', 'campoEscarapela', 'min', 'max', 'minLabel', 'maxLabel'];

    /** Quién puede ver el catálogo completo de permisos (la pantalla de permisos). */
    private const VE_CATALOGO = ['GESTION_ROL_PERMISOS', 'GESTION_ROLES'];

    /** @var \Illuminate\Support\Collection|null */
    private $permisosCache = null;

    /**
     * Permisos efectivos del usuario EN LA EMPRESA de su token, leídos de la
     * base en cada petición (no del token): si un administrador le quita o le
     * da un permiso, cuenta desde la siguiente consulta. Incluye los permisos
     * de todos sus roles y los asignados directamente a su activación.
     */
    private function permisos()
    {
        // Misma fuente que el middleware `permiso.efectivo` de las rutas de escritura.
        return $this->permisosCache ??= \App\Util\PermisosEfectivos::actuales();
    }

    private function puede(string $consulta): bool
    {
        if (!array_key_exists($consulta, self::CAPACIDADES)) {
            return false;
        }
        $requeridos = self::CAPACIDADES[$consulta];
        return $requeridos === null || $this->permisos()->intersect($requeridos)->isNotEmpty();
    }

    private function negado(string $consulta, string $texto): JsonResponse
    {
        return response()->json(['error' => $texto, 'consulta' => $consulta], 403);
    }

    /**
     * Obtiene las clases del día actual para el usuario autenticado.
     */
    public function clasesHoy(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;
            $hoy = Carbon::now();
            $idDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

            // Intentar obtener como instructor
            $contrato = KeyUtil::lastContractActive();
            if ($contrato) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->where('idContrato', $contrato->id)
                    ->where('idDia', $idDia)
                    ->get();

                return response()->json([
                    'role' => 'instructor',
                    'data' => $clases
                ]);
            }

            // Intentar obtener como estudiante
            $matriculas = MatriculaAcademica::whereHas('matricula', function($q) use ($idPersona) {
                $q->where('idPersona', $idPersona);
            })->pluck('idFicha');

            if ($matriculas->isNotEmpty()) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->whereIn('idFicha', $matriculas)
                    ->where('idDia', $idDia)
                    ->get();

                return response()->json([
                    'role' => 'estudiante',
                    'data' => $clases
                ]);
            }

            return response()->json(['data' => [], 'message' => 'No se encontraron clases para hoy']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las notas (calificaciones) del estudiante autenticado.
     */
    public function misNotas(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;

            $calificaciones = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->where('m.idPersona', $idPersona)
                ->whereNotNull('ca.fechaCalificacion')
                ->select([
                    'a.id as idActividad',
                    'a.tituloActividad',
                    'a.tipoActividad',
                    'mat.nombreMateria',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.fechaCalificacion',
                    'ca.fechaFinal',
                ])
                ->orderByDesc('ca.fechaCalificacion')
                ->get();

            return response()->json($calificaciones);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las actividades pendientes para el estudiante autenticado.
     */
    public function pendientes(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;

            $pendientes = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->where('m.idPersona', $idPersona)
                ->where(function($q) {
                    $q->whereNull('ca.calificacionNumerica')
                      ->orWhere('ca.calificacionNumerica', '');
                })
                ->where(function($q) {
                    $q->whereNull('ca.archivo')
                      ->orWhere('ca.archivo', '');
                })
                ->select([
                    'a.id as idActividad',
                    'a.tituloActividad',
                    'a.tipoActividad',
                    'a.descripcionActividad',
                    'ca.fechaFinal as fechaLimite',
                    'mat.nombreMateria'
                ])
                ->orderBy('ca.fechaFinal')
                ->get();

            return response()->json($pendientes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene todas las fichas activas (para administradores/coordinadores).
     */
    public function fichasActivas(Request $request): JsonResponse
    {
        try {
            if (!$this->puede('fichas')) {
                return $this->negado('fichas', 'No autorizado para ver fichas activas');
            }

            $idCompany = KeyUtil::idCompany();
            
            $hoy = now()->toDateString();
            $fichas = Ficha::with(['aperturarPrograma.programa', 'jornada'])
                ->where('idRegional', $idCompany)
                ->get()
                ->map(function ($f) use ($hoy) {
                    $apertura = $f->aperturarPrograma;
                    $fin = $apertura?->fechaFinalClases;
                    // Activa: la apertura del programa está ACTIVA y sus clases no han terminado.
                    $activa = $apertura
                        && strtoupper((string) $apertura->estado) === 'ACTIVO'
                        && (!$fin || substr((string) $fin, 0, 10) >= $hoy);
                    return [
                        'id' => $f->id,
                        'codigo' => $f->codigo,
                        'programa' => $apertura?->programa?->nombrePrograma ?? 'N/A',
                        'jornada' => $f->jornada?->nombre ?? 'N/A',
                        'estado_apertura' => $apertura?->estado,
                        'fin_clases' => $fin,
                        'activa' => (bool) $activa,
                    ];
                });

            return response()->json($fichas);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las entregas (respuestas) de una actividad.
     */
    public function getEntregas(int $idActividad): JsonResponse
    {
        try {
            // Las entregas traen nombres y notas de otros aprendices: no son para
            // cualquier usuario con sesión, y nunca de otra empresa.
            if (!$this->puede('entregas')) {
                return $this->negado('entregas', 'No autorizado para ver entregas');
            }
            $actividad = DB::table('actividades')->where('id', $idActividad)->first();
            if (!$actividad || (int) $actividad->idCompany !== (int) KeyUtil::idCompany()) {
                return response()->json(['error' => 'Actividad no encontrada'], 404);
            }
            $entregas = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('persona as p', 'm.idPersona', '=', 'p.id')
                ->where('ca.idActividad', $idActividad)
                ->select([
                    'p.nombre1',
                    'p.apellido1',
                    'ca.archivo',
                    'ca.fechaCalificacion',
                    'ca.calificacionNumerica'
                ])
                ->get();

            return response()->json($entregas);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene la cantidad de estudiantes matriculados en una ficha.
     */
    public function getEstudiantesFicha(int $idFicha): JsonResponse
    {
        return $this->estudiantesDe(Ficha::find($idFicha), (string) $idFicha);
    }

    /**
     * Estudiantes de una ficha por su CÓDIGO (el número que conoce la gente,
     * p. ej. "2558"), no por el id interno de la tabla.
     */
    public function getEstudiantesFichaPorCodigo(string $codigo): JsonResponse
    {
        return $this->estudiantesDe(Ficha::where('codigo', trim($codigo))->first(), $codigo);
    }

    private function estudiantesDe(?Ficha $ficha, string $buscada): JsonResponse
    {
        try {
            if (!$this->puede('estudiantes')) {
                return $this->negado('estudiantes', 'No autorizado para ver estudiantes de una ficha');
            }
            if (!$ficha || (int) $ficha->idRegional !== (int) KeyUtil::idCompany()) {
                return response()->json(['error' => 'Ficha no encontrada', 'ficha' => $buscada], 404);
            }
            $total = MatriculaAcademica::where('idFicha', $ficha->id)->distinct('idMatricula')->count('idMatricula');

            return response()->json([
                'ficha_id' => $ficha->id,
                'codigo' => $ficha->codigo,
                'total_estudiantes' => $total
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el horario semanal completo del usuario autenticado.
     */
    public function horarioSemana(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;

            // Intentar obtener como instructor
            $contrato = KeyUtil::lastContractActive();
            if ($contrato) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->where('idContrato', $contrato->id)
                    ->orderBy('idDia')
                    ->orderBy('horaInicial')
                    ->get();

                return response()->json([
                    'role' => 'instructor',
                    'data' => $clases
                ]);
            }

            // Intentar obtener como estudiante
            $matriculas = MatriculaAcademica::whereHas('matricula', function($q) use ($idPersona) {
                $q->where('idPersona', $idPersona);
            })->pluck('idFicha');

            if ($matriculas->isNotEmpty()) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->whereIn('idFicha', $matriculas)
                    ->orderBy('idDia')
                    ->orderBy('horaInicial')
                    ->get();

                return response()->json([
                    'role' => 'estudiante',
                    'data' => $clases
                ]);
            }

            return response()->json(['data' => [], 'message' => 'No se encontró horario semanal']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene un resumen general para administradores.
     */
    public function resumenAdmin(Request $request): JsonResponse
    {
        try {
            if (!$this->puede('resumen')) {
                return $this->negado('resumen', 'No autorizado para ver resumen administrativo');
            }

            $idCompany = KeyUtil::idCompany();
            
            $instructoresActivos = Contract::where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->count();

            $fichasEnEjecucion = Ficha::where('idRegional', $idCompany)->count();

            // Count enrolled students via fichas belonging to this regional
            $fichaIds = Ficha::where('idRegional', $idCompany)->pluck('id');
            $aprendicesMatriculados = $fichaIds->isNotEmpty()
                ? MatriculaAcademica::whereIn('idFicha', $fichaIds)->distinct('idMatricula')->count('idMatricula')
                : 0;

            $sedesActivas = Sede::where('idEmpresa', $idCompany)->count();

            $regional = Company::find($idCompany);

            return response()->json([
                'regional' => $regional->nombre ?? 'N/A',
                'instructores_activos' => $instructoresActivos,
                'fichas_en_ejecucion' => $fichasEnEjecucion,
                'aprendices_matriculados' => $aprendicesMatriculados,
                'sedes_activas' => $sedesActivas,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene un resumen de la nómina (total de salarios activos).
     */
    public function nominaResumen(Request $request): JsonResponse
    {
        try {
            if (!$this->puede('nomina')) {
                return $this->negado('nomina', 'No autorizado para ver nómina');
            }

            $idCompany = KeyUtil::idCompany();
            
            $contratos = Contract::with('salario')
                ->where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->get();

            $totalSalarios = $contratos->sum(fn($c) => $c->salario->valor ?? 0);

            return response()->json([
                'total_nomina_mensual' => $totalSalarios,
                'total_contratos' => $contratos->count(),
                'moneda' => 'COP',
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista todos los instructores activos con su información básica.
     */
    public function listaInstructores(Request $request): JsonResponse
    {
        try {
            if (!$this->puede('instructores')) {
                return $this->negado('instructores', 'No autorizado para ver lista de instructores');
            }
            $idCompany = KeyUtil::idCompany();
            
            $hoy = now()->toDateString();
            $instructores = Contract::with(['persona', 'tipoContrato'])
                ->where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->get()
                ->map(function($c) use ($hoy) {
                    $tipo = $c->tipoContrato ? $c->tipoContrato->nombreTipoContrato : null;
                    $fin = $c->fechaFinalContrato ? substr((string) $c->fechaFinalContrato, 0, 10) : null;
                    // Término indefinido: no tiene fecha de fin real; la base guarda
                    // una fecha de relleno lejana (p. ej. 2070-12-31).
                    $indefinido = ($tipo && stripos($tipo, 'INDEFINIDO') !== false) || ($fin && $fin >= '2060-01-01');
                    return [
                        'id' => $c->id,
                        'nombre' => $c->persona ? ($c->persona->nombre1 . ' ' . $c->persona->apellido1) : 'Sin nombre',
                        'tipo' => $tipo ?? 'N/A',
                        'fecha_fin' => $indefinido ? null : $fin,
                        'indefinido' => $indefinido,
                        // Estado 1 con la fecha de fin ya pasada: el contrato terminó
                        // y nadie lo cerró en el sistema.
                        'vigente' => $indefinido || !$fin || $fin >= $hoy,
                    ];
                });

            return response()->json($instructores);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene contratos próximos a vencer (30 días).
     */
    public function contratosVencimiento(Request $request): JsonResponse
    {
        try {
            if (!$this->puede('contratos')) {
                return $this->negado('contratos', 'No autorizado para ver vencimientos de contrato');
            }
            $idCompany = KeyUtil::idCompany();
            $fechaLimite = now()->addDays(30);
            
            $contratos = Contract::with(['persona', 'tipoContrato'])
                ->where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->whereBetween('fechaFinalContrato', [now(), $fechaLimite])
                ->orderBy('fechaFinalContrato')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'numeroContrato' => $c->numeroContrato,
                    'fechaFinalContrato' => $c->fechaFinalContrato,
                    'tipo' => $c->tipoContrato?->nombreTipoContrato,
                    'persona' => $c->persona ? [
                        'nombre1' => $c->persona->nombre1,
                        'apellido1' => $c->persona->apellido1,
                    ] : null,
                ]);

            return response()->json($contratos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Capacidades y consultas de gestión académica ─────────────────────────

    /**
     * Qué puede hacer Nexo por este usuario, según sus permisos efectivos.
     *
     * - consultas: las consultas de esta API que el usuario tiene habilitadas.
     * - secciones: TODAS las pantallas a las que tiene permiso (las que tienen
     *   ruta), con su nombre. Un permiso nuevo con ruta aparece aquí sin tocar
     *   código: Nexo puede llevar al usuario, aunque todavía no sepa consultarlo.
     */
    public function capacidades(): JsonResponse
    {
        $permisos = $this->permisos();
        $consultas = collect(array_keys(self::CAPACIDADES))->filter(fn ($c) => $this->puede($c))->values();
        $secciones = DB::table('permissions as p')
            ->leftJoin('permissions as padre', 'padre.id', '=', 'p.idPermissionPadre')
            ->whereIn('p.name', $permisos)
            ->whereNotNull('p.path')
            ->where('p.path', '!=', '')
            ->where('p.path', '!=', '/')
            ->orderBy('p.id')
            ->get(['p.name', 'p.description', 'p.path', 'padre.description as grupo'])
            ->map(fn ($p) => ['permiso' => $p->name, 'nombre' => $p->description, 'ruta' => $p->path, 'grupo' => $p->grupo]);

        $respuesta = [
            'permisos' => $permisos,
            'consultas' => $consultas,
            'secciones' => $secciones,
        ];
        // "¿Qué permisos NO tengo?" sólo se responde a quien ya puede ver el
        // catálogo de permisos en School (la pantalla de roles y permisos).
        if ($permisos->intersect(self::VE_CATALOGO)->isNotEmpty()) {
            $respuesta['catalogo'] = DB::table('permissions as p')
                ->leftJoin('permissions as padre', 'padre.id', '=', 'p.idPermissionPadre')
                ->orderBy('p.id')
                ->get(['p.name as permiso', 'p.description as nombre', 'p.path as ruta', 'padre.description as grupo']);
        }
        return response()->json($respuesta);
    }

    public function centrosFormacion(): JsonResponse
    {
        if (!$this->puede('centros')) {
            return $this->negado('centros', 'No autorizado para consultar centros de formación');
        }
        $centros = DB::table('centroFormacion as c')
            ->leftJoin('ciudad as ci', 'ci.id', '=', 'c.idCiudad')
            ->where('c.idEmpresa', KeyUtil::idCompany())
            ->orderBy('c.nombre')
            ->get(['c.id', 'c.nombre', 'c.direccion', 'c.telefono', 'c.subdirector', 'ci.descripcion as ciudad']);
        return response()->json($centros);
    }

    public function sedes(): JsonResponse
    {
        if (!$this->puede('sedes')) {
            return $this->negado('sedes', 'No autorizado para consultar sedes');
        }
        $sedes = DB::table('sedes as s')
            ->leftJoin('centroFormacion as c', 'c.id', '=', 's.idCentroFormacion')
            ->where('s.idEmpresa', KeyUtil::idCompany())
            ->orderBy('s.nombre')
            ->get(['s.id', DB::raw('COALESCE(s.nombreSede, s.nombre) as nombre'), 's.direccion', 'c.nombre as centro']);
        return response()->json($sedes);
    }

    /** Ambientes de formación: la infraestructura de las sedes de la empresa. */
    public function ambientes(): JsonResponse
    {
        if (!$this->puede('ambientes')) {
            return $this->negado('ambientes', 'No autorizado para consultar ambientes');
        }
        $ambientes = DB::table('infraestructura as i')
            ->join('sedes as s', 's.id', '=', 'i.idSede')
            ->leftJoin('tiposinfraestructura as t', 't.id', '=', 'i.idTipoInfraestructura')
            ->where('s.idEmpresa', KeyUtil::idCompany())
            ->orderBy('s.nombre')->orderBy('i.nombreInfraestructura')
            ->get(['i.id', 'i.nombreInfraestructura as nombre', 'i.capacidad',
                   DB::raw('COALESCE(s.nombreSede, s.nombre) as sede'), 't.nombre as tipo']);
        return response()->json($ambientes);
    }

    /**
     * Periodos académicos. Con permiso de gestión: los de la empresa. Sin él:
     * los periodos de las fichas en que el usuario está matriculado (lo suyo).
     */
    public function periodos(): JsonResponse
    {
        $hoy = now()->toDateString();
        $q = DB::table('periodo as p');
        if ($this->puede('periodos')) {
            $alcance = 'empresa';
            $q->where('p.idEmpresa', KeyUtil::idCompany());
        } else {
            $alcance = 'propios';
            $user = KeyUtil::user();
            $idsPeriodo = DB::table('matricula as m')
                ->join('ficha as f', 'f.id', '=', 'm.idFicha')
                ->join('aperturarprograma as ap', 'ap.id', '=', 'f.idAsignacion')
                ->where('m.idPersona', $user?->idpersona)
                ->pluck('ap.idPeriodo');
            $q->whereIn('p.id', $idsPeriodo);
        }
        $periodos = $q->orderByDesc('p.fechaInicial')
            ->get(['p.id', 'p.nombrePeriodo as nombre', 'p.fechaInicial', 'p.fechaFinal'])
            ->map(fn ($p) => (array) $p + [
                'vigente' => $p->fechaInicial && $p->fechaFinal
                    && substr((string) $p->fechaInicial, 0, 10) <= $hoy && substr((string) $p->fechaFinal, 0, 10) >= $hoy,
            ]);
        return response()->json(['alcance' => $alcance, 'periodos' => $periodos]);
    }

    public function jornadas(): JsonResponse
    {
        if (!$this->puede('jornadas')) {
            return $this->negado('jornadas', 'No autorizado para consultar jornadas');
        }
        $jornadas = DB::table('jornadas as j')
            ->join('centroFormacion as c', 'c.id', '=', 'j.idCentroFormacion')
            ->where('c.idEmpresa', KeyUtil::idCompany())
            ->orderBy('j.horaInicial')
            ->get(['j.id', 'j.nombreJornada as nombre', 'j.horaInicial', 'j.horaFinal', 'j.estado', 'c.nombre as centro']);
        return response()->json($jornadas);
    }

    /**
     * Planeación académica: las redes de conocimiento y los programas que la
     * empresa tiene en cada una (es lo que muestra la pantalla «Planeación»).
     */
    public function planeacion(): JsonResponse
    {
        if (!$this->puede('planeacion')) {
            return $this->negado('planeacion', 'No autorizado para consultar la planeación');
        }
        $idCompany = KeyUtil::idCompany();
        $redes = DB::table('red as r')
            ->leftJoin('programa as p', function ($j) use ($idCompany) {
                $j->on('p.idRed', '=', 'r.id')->where('p.idCompany', '=', $idCompany);
            })
            ->groupBy('r.id', 'r.nombre')
            ->orderBy('r.nombre')
            ->get(['r.id', 'r.nombre', DB::raw('COUNT(p.id) as programas')]);
        $sinRed = DB::table('programa')->where('idCompany', $idCompany)->whereNull('idRed')->count();
        return response()->json(['redes' => $redes, 'programas_sin_red' => $sinRed]);
    }

    // ── Fase 5: más módulos consultables y una operación de escritura ────────

    public function regionales(): JsonResponse
    {
        if (!$this->puede('regionales')) {
            return $this->negado('regionales', 'No autorizado para consultar regionales');
        }
        $regionales = DB::table('regional as r')
            ->leftJoin('departamento as d', 'd.id', '=', 'r.idDepartamento')
            ->orderBy('r.nombre')
            ->get(['r.id', 'r.nombre', 'r.telefono', 'r.direccion', 'd.descripcion as departamento']);
        return response()->json($regionales);
    }

    public function eventos(Request $request): JsonResponse
    {
        if (!$this->puede('eventos')) {
            return $this->negado('eventos', 'No autorizado para consultar eventos');
        }
        $q = DB::table('evento')->where('idCompany', KeyUtil::idCompany());
        if ($request->filled('estado')) {
            $q->where('estado', strtoupper((string) $request->input('estado')));
        }
        $eventos = $q->orderByDesc('fechaInicial')->limit(100)
            ->get(['idEvento as id', 'nombre', 'fechaInicial', 'fechaFinal', 'hora', 'hora_final', 'estado', 'tipoEvento',
                   'esPublico', 'idFormularioInterno', 'created_at']);
        return response()->json($eventos);
    }

    public function formularios(Request $request): JsonResponse
    {
        if (!$this->puede('formularios')) {
            return $this->negado('formularios', 'No autorizado para consultar formularios');
        }
        $q = DB::table('formularios')->where('idCompany', KeyUtil::idCompany());
        if ($request->filled('estado')) {
            $q->where('estado', strtolower((string) $request->input('estado')));
        }
        $formularios = $q->orderByDesc('created_at')->limit(100)
            ->get(['id', 'titulo as nombre', 'estado', 'fechaLimite', 'slug', 'requiereAutenticacion', 'created_at',
                   DB::raw('(select count(*) from formulario_preguntas p where p.idFormulario = formularios.id) as preguntas')]);
        return response()->json($formularios);
    }

    /** Carpetas viajeras de las personas que pertenecen a la empresa del usuario. */
    public function carpetasViajeras(): JsonResponse
    {
        if (!$this->puede('carpetas')) {
            return $this->negado('carpetas', 'No autorizado para consultar carpetas viajeras');
        }
        $carpetas = DB::table('carpetas_viajeras as c')
            ->join('persona as p', 'p.id', '=', 'c.persona_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('activation_company_users as a')
                    ->join('usuario as u', 'u.id', '=', 'a.user_id')
                    ->whereColumn('u.idpersona', 'p.id')
                    ->where('a.company_id', KeyUtil::idCompany());
            })
            ->orderByDesc('c.id')->limit(100)
            ->get(['c.id', DB::raw("TRIM(CONCAT_WS(' ', p.nombre1, p.apellido1)) as nombre"), 'c.modulo', 'c.nivel_academico',
                   'c.total_horas', 'c.aprobada']);
        return response()->json($carpetas);
    }

    public function configuracionPagos(): JsonResponse
    {
        if (!$this->puede('configuracion_pagos')) {
            return $this->negado('configuracion_pagos', 'No autorizado para consultar la configuración de pagos');
        }
        $config = DB::table('configuracionPago')->where('idCompany', KeyUtil::idCompany())->orderBy('titulo')
            ->get(['id', 'titulo as nombre', 'valor', 'estado', 'tipoMovimiento', 'frecuenciaPago', 'obligatorio']);
        return response()->json($config);
    }

    /**
     * Crea un evento desde la conversación.
     *
     * El endpoint del front (`POST eventos-multimedia`) valida los datos pero no
     * los permisos: cualquier sesión podía crear eventos. Éste exige el permiso
     * efectivo y la empresa del token, valida lo mínimo obligatorio y después
     * reutiliza la MISMA lógica de creación del módulo de eventos.
     */
    public function crearEvento(Request $request): JsonResponse
    {
        if (!$this->puede('crear_evento')) {
            return $this->negado('crear_evento', 'No autorizado para crear eventos');
        }
        $request->validate([
            'nombre' => 'required|string|max:255',
            'fechaInicial' => 'required|date|after_or_equal:today',
            'hora' => 'required|date_format:H:i',
            'hora_final' => 'nullable|date_format:H:i',
            'descripcion' => 'nullable|string|max:2000',
            'tipoEvento' => 'nullable|in:PRESENCIAL,VIRTUAL,HIBRIDO',
        ]);
        $request->merge(['estado' => 'PENDIENTE', 'crearHistoria' => false]);
        return app(\App\Http\Controllers\EventoController::class)->store($request);
    }

    // ── Fase 6: más consultas y crear formularios ───────────────────────────

    /**
     * Pagos de contratos pendientes por revisar, SÓLO de la empresa del token.
     * Mismo criterio de estados que la pantalla (4 y 11 vencidos; 7 en la
     * ventana de -5 a +10 días), pero la consulta original no filtraba empresa.
     */
    public function pagosPendientes(): JsonResponse
    {
        if (!$this->puede('pagos_pendientes')) {
            return $this->negado('pagos_pendientes', 'No autorizado para consultar pagos pendientes');
        }
        $hoy = now()->toDateString();
        $pagos = DB::table('pagos as p')
            ->join('transaccion as t', 't.id', '=', 'p.idTransaccion')
            ->join('contrato as c', 'c.id', '=', 't.contrato_id')
            ->leftJoin('persona as pe', 'pe.id', '=', 'c.idpersona')
            ->leftJoin('estado as e', 'e.id', '=', 'p.idEstado')
            ->where('c.idempresa', KeyUtil::idCompany())
            ->where(function ($q) use ($hoy) {
                $q->where(function ($a) use ($hoy) {
                    $a->whereIn('p.idEstado', [4, 11])->whereDate('p.fechaPago', '<=', $hoy);
                })->orWhere(function ($b) {
                    $b->where('p.idEstado', 7)
                      ->whereDate('p.fechaPago', '>=', now()->subDays(5)->toDateString())
                      ->whereDate('p.fechaPago', '<=', now()->addDays(10)->toDateString());
                });
            })
            ->orderByDesc('p.fechaPago')->limit(100)
            ->get(['p.id', DB::raw("TRIM(CONCAT_WS(' ', pe.nombre1, pe.apellido1)) as nombre"), 'p.valor',
                   'p.fechaPago', 'e.estado']);
        return response()->json($pagos);
    }

    /** Solicitudes de inscripción: la lógica de la pantalla, con la empresa del TOKEN. */
    public function solicitudesInscripcion(): JsonResponse
    {
        if (!$this->puede('solicitudes_inscripcion')) {
            return $this->negado('solicitudes_inscripcion', 'No autorizado para consultar solicitudes de inscripción');
        }
        // La ruta original acepta `idCompany` en la petición; aquí no se le pasa.
        $respuesta = app(\App\Http\Controllers\gestion_pago\PagoController::class)
            ->getSolicitudesInscripcion(new Request());
        return $respuesta instanceof JsonResponse ? $respuesta : response()->json($respuesta);
    }

    public function configuracionInscripcion(): JsonResponse
    {
        if (!$this->puede('configuracion_inscripcion')) {
            return $this->negado('configuracion_inscripcion', 'No autorizado para consultar la configuración de inscripción');
        }
        $c = Company::find(KeyUtil::idCompany());
        $formulario = $c && $c->idFormularioInscripcion
            ? DB::table('formularios')->where('id', $c->idFormularioInscripcion)->value('titulo') : null;
        return response()->json([
            'inscripcionHabilitada' => (bool) ($c->inscripcionHabilitada ?? false),
            'fechaInicioInscripcion' => $c->fechaInicioInscripcion ?? null,
            'fechaFinInscripcion' => $c->fechaFinInscripcion ?? null,
            'formulario' => $formulario,
        ]);
    }

    public function multimedia(Request $request): JsonResponse
    {
        if (!$this->puede('multimedia')) {
            return $this->negado('multimedia', 'No autorizado para consultar historias y reels');
        }
        $q = DB::table('multimedia_historias')->where('idCompany', KeyUtil::idCompany());
        if (in_array($request->input('tipo'), ['historia', 'reel'], true)) {
            $q->where('tipo', $request->input('tipo'));
        }
        return response()->json($q->orderByDesc('created_at')->limit(100)
            ->get(['id', DB::raw("COALESCE(NULLIF(nombre, ''), descripcion, CONCAT('#', id)) as nombre"), 'tipo', 'created_at']));
    }

    /** Informes de actividades de instructores (pantalla «Informe de Pago»). */
    public function informesInstructores(): JsonResponse
    {
        if (!$this->puede('informes_instructores')) {
            return $this->negado('informes_instructores', 'No autorizado para consultar informes de instructores');
        }
        $informes = DB::table('actividadesinstructores as a')
            ->join('contrato as c', 'c.id', '=', 'a.idContrato')
            ->leftJoin('persona as pe', 'pe.id', '=', 'c.idpersona')
            ->where('c.idempresa', KeyUtil::idCompany())
            ->orderByDesc('a.fechaInicial')->limit(100)
            ->get(['a.id', DB::raw("TRIM(CONCAT_WS(' ', pe.nombre1, pe.apellido1)) as nombre"), 'a.descripcion',
                   'a.fechaInicial', 'a.fechaFinal', 'a.numeroHoras']);
        return response()->json($informes);
    }

    /**
     * Crea un formulario en borrador: el mismo store del módulo, con permiso y
     * empresa. Fase 7: puede traer sus preguntas (validadas contra el contrato
     * del constructor) para crear de una vez lo que se armó en la conversación.
     */
    public function crearFormulario(Request $request): JsonResponse
    {
        if (!$this->puede('crear_formulario')) {
            return $this->negado('crear_formulario', 'No autorizado para crear formularios');
        }
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
        ]);
        $preguntas = $this->preguntasValidas($request->input('preguntas', []));
        if ($preguntas instanceof JsonResponse) {
            return $preguntas;
        }
        $request->merge(['estado' => 'borrador', 'preguntas' => $preguntas]);
        $res = app(\App\Http\Controllers\FormularioController::class)->store($request);
        if ($res->getStatusCode() === 201) {
            $f = \App\Models\Formulario::with('preguntas.opciones')->find($res->getData(true)['id'] ?? 0);
            if ($f) {
                return response()->json($this->estructura($f), 201);
            }
        }
        return $res;
    }

    // ── Fase 7: constructor conversacional y vínculo con eventos ────────────

    /** El formulario sólo si es de la empresa del token (null si no existe o es de otra). */
    private function formularioDeEmpresa($id): ?\App\Models\Formulario
    {
        return \App\Models\Formulario::with('preguntas.opciones')
            ->where('idCompany', KeyUtil::idCompany())->find((int) $id);
    }

    private function eventoDeEmpresa($id): ?\App\Models\Evento
    {
        return \App\Models\Evento::where('idCompany', KeyUtil::idCompany())->find((int) $id);
    }

    private function noExiste(string $que): JsonResponse
    {
        // Mismo 404 si no existe o si es de otra institución: no se revela cuál.
        return response()->json(['error' => "No encontré ese {$que} en tu institución"], 404);
    }

    /**
     * Huella de la estructura: cambia con cualquier cambio de título, estado,
     * preguntas u opciones, hecho desde Nexo o desde el constructor. Sirve para
     * detectar ediciones concurrentes (el updated_at del formulario no cambia
     * cuando sólo cambian sus preguntas).
     */
    private function version(\App\Models\Formulario $f): string
    {
        $p = $f->preguntas->map(fn ($q) => [$q->id, $q->tipo, $q->titulo, $q->descripcion, (bool) $q->esObligatoria,
            (int) $q->orden, $q->configuracion, $q->opciones->map(fn ($o) => [$o->id, $o->texto, (int) $o->orden])->all()]);
        return sha1(json_encode([$f->titulo, $f->descripcion, $f->estado, $p->all()]));
    }

    private function estructura(\App\Models\Formulario $f): array
    {
        $eventos = DB::table('evento')->where('idCompany', KeyUtil::idCompany())
            ->where('idFormularioInterno', $f->id)->orderBy('fechaInicial')
            ->get(['idEvento as id', 'nombre', 'fechaInicial', 'hora', 'estado']);
        return [
            'id' => $f->id,
            'titulo' => $f->titulo,
            'descripcion' => $f->descripcion,
            'estado' => $f->estado,
            'slug' => $f->slug,
            'requiereAutenticacion' => (bool) $f->requiereAutenticacion,
            'version' => $this->version($f),
            'respuestas' => DB::table('formulario_respuestas')->where('idFormulario', $f->id)->count(),
            'eventos' => $eventos,
            'preguntas' => $f->preguntas->map(fn ($q) => [
                'id' => $q->id,
                'tipo' => $q->tipo,
                'titulo' => $q->titulo,
                'descripcion' => $q->descripcion,
                'esObligatoria' => (bool) $q->esObligatoria,
                'orden' => (int) $q->orden,
                'configuracion' => $q->configuracion,
                'opciones' => $q->opciones->map(fn ($o) => ['id' => $o->id, 'texto' => $o->texto, 'orden' => (int) $o->orden])->values(),
            ])->values(),
        ];
    }

    /**
     * Valida las preguntas con el contrato del constructor del front
     * (formBuilderTypes.ts): tipos del enum, opciones en los tipos de selección,
     * escala con min/max y sólo las claves de configuración que el front conoce.
     * Devuelve las preguntas normalizadas (orden 1..n) o un 422.
     */
    private function preguntasValidas($preguntas, ?\App\Models\Formulario $f = null)
    {
        if (!is_array($preguntas)) {
            return response()->json(['errors' => ['preguntas' => ['deben ser una lista']]], 422);
        }
        if (count($preguntas) > 100) {
            return response()->json(['errors' => ['preguntas' => ['un formulario admite hasta 100 preguntas']]], 422);
        }
        $propias = $f ? $f->preguntas->keyBy('id') : collect();
        $errores = [];
        $salida = [];
        foreach (array_values($preguntas) as $i => $p) {
            $n = $i + 1;
            $tipo = $p['tipo'] ?? null;
            $titulo = trim((string) ($p['titulo'] ?? ''));
            if (!in_array($tipo, self::TIPOS_PREGUNTA, true)) {
                $errores["preguntas.$n.tipo"] = ["la pregunta $n tiene un tipo que el constructor no admite"];
                continue;
            }
            if ($titulo === '' || mb_strlen($titulo) > 1000) {
                $errores["preguntas.$n.titulo"] = ["la pregunta $n necesita un enunciado (máx. 1000 caracteres)"];
                continue;
            }
            $id = $p['id'] ?? null;
            // Un id ajeno a este formulario no puede colarse: se trata como pregunta nueva.
            if ($id !== null && !$propias->has((int) $id)) {
                $id = null;
            }
            $config = array_intersect_key((array) ($p['configuracion'] ?? []), array_flip(self::CONFIG_PERMITIDA));
            if ($tipo === 'escala_lineal') {
                $config = ['min' => (int) ($config['min'] ?? 1), 'max' => (int) ($config['max'] ?? 5),
                           'minLabel' => (string) ($config['minLabel'] ?? ''), 'maxLabel' => (string) ($config['maxLabel'] ?? '')];
                if ($config['min'] < 0 || $config['max'] > 10 || $config['min'] >= $config['max']) {
                    $errores["preguntas.$n.configuracion"] = ["la escala de la pregunta $n no es válida"];
                    continue;
                }
            } elseif ($tipo !== 'texto_corto') {
                // Igual que el front: la configuración de respuesta corta no se arrastra a otro tipo.
                $config = [];
            }
            $opciones = [];
            if (in_array($tipo, self::TIPOS_CON_OPCIONES, true)) {
                $propiasOp = $id ? $propias[(int) $id]->opciones->keyBy('id') : collect();
                foreach (array_values((array) ($p['opciones'] ?? [])) as $j => $o) {
                    $texto = trim((string) (is_array($o) ? ($o['texto'] ?? '') : $o));
                    if ($texto === '') {
                        continue;
                    }
                    $oid = is_array($o) ? ($o['id'] ?? null) : null;
                    $op = ['texto' => mb_substr($texto, 0, 500), 'orden' => $j + 1];
                    if ($oid !== null && $propiasOp->has((int) $oid)) {
                        $op['id'] = (int) $oid;
                    }
                    $opciones[] = $op;
                }
                if (!$opciones) {
                    $errores["preguntas.$n.opciones"] = ["la pregunta $n («{$titulo}») necesita al menos una opción"];
                    continue;
                }
            }
            $q = ['tipo' => $tipo, 'titulo' => $titulo, 'descripcion' => $p['descripcion'] ?? null,
                  'esObligatoria' => (bool) ($p['esObligatoria'] ?? false), 'orden' => $n,
                  'configuracion' => $config ?: null, 'opciones' => $opciones];
            if ($id !== null) {
                $q['id'] = (int) $id;
            }
            $salida[] = $q;
        }
        if ($errores) {
            return response()->json(['message' => 'Preguntas no válidas', 'errors' => $errores], 422);
        }
        return $salida;
    }

    /** Estructura real del formulario (con su huella de versión y eventos vinculados). */
    public function formulario($id): JsonResponse
    {
        if (!$this->puede('formularios')) {
            return $this->negado('formularios', 'No autorizado para consultar formularios');
        }
        $f = $this->formularioDeEmpresa($id);
        return $f ? response()->json($this->estructura($f)) : $this->noExiste('formulario');
    }

    /**
     * Guarda título, descripción y preguntas desde la conversación con el MISMO
     * update del constructor (sincroniza preguntas y opciones por id). No toca el
     * estado ni el resto de la configuración: el update original los reemplaza
     * con lo que llegue, así que aquí se le pasan los valores actuales.
     * `version` = la huella que Nexo leyó: si alguien cambió el formulario
     * después (otra pestaña, el constructor), se responde 409 y no se pisa nada.
     */
    public function guardarFormulario(Request $request, $id): JsonResponse
    {
        if (!$this->puede('editar_formulario')) {
            return $this->negado('editar_formulario', 'No autorizado para modificar formularios');
        }
        $f = $this->formularioDeEmpresa($id);
        if (!$f) {
            return $this->noExiste('formulario');
        }
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'version' => 'required|string',
        ]);
        if ($request->input('version') !== $this->version($f)) {
            return response()->json(['error' => 'El formulario cambió desde la última vez que lo leí',
                                     'conflicto' => true, 'actual' => $this->estructura($f)], 409);
        }
        $preguntas = $this->preguntasValidas($request->input('preguntas', []), $f);
        if ($preguntas instanceof JsonResponse) {
            return $preguntas;
        }
        $sub = Request::create($request->path(), 'PUT', [
            'titulo' => $request->input('titulo'),
            'descripcion' => $request->input('descripcion'),
            'colorTema' => $f->colorTema,
            'estado' => $f->estado,
            'requiereAutenticacion' => (bool) $f->requiereAutenticacion,
            'preguntas' => $preguntas,
        ]);
        $sub->setUserResolver($request->getUserResolver());
        $res = app(\App\Http\Controllers\FormularioController::class)->update($sub, $f->id);
        if ($res->getStatusCode() !== 200) {
            return $res;
        }
        return response()->json($this->estructura($this->formularioDeEmpresa($f->id)));
    }

    /** Publica o devuelve a borrador (nunca se publica como efecto de otra operación). */
    public function estadoFormulario(Request $request, $id): JsonResponse
    {
        if (!$this->puede('publicar_formulario')) {
            return $this->negado('publicar_formulario', 'No autorizado para publicar formularios');
        }
        $f = $this->formularioDeEmpresa($id);
        if (!$f) {
            return $this->noExiste('formulario');
        }
        $request->validate(['estado' => 'required|in:publicado,borrador,cerrado']);
        if ($request->input('estado') === 'publicado' && $f->preguntas->isEmpty()) {
            return response()->json(['errors' => ['estado' => ['no se puede publicar un formulario sin preguntas']]], 422);
        }
        $f->update(['estado' => $request->input('estado')]);
        return response()->json($this->estructura($this->formularioDeEmpresa($f->id)));
    }

    /** Detalle de un evento con su formulario de inscripción y cuántos se han inscrito. */
    public function evento($id): JsonResponse
    {
        if (!$this->puede('eventos')) {
            return $this->negado('eventos', 'No autorizado para consultar eventos');
        }
        $e = $this->eventoDeEmpresa($id);
        if (!$e) {
            return $this->noExiste('evento');
        }
        return response()->json($this->detalleEvento($e));
    }

    private function detalleEvento(\App\Models\Evento $e): array
    {
        $form = $e->idFormularioInterno
            ? \App\Models\Formulario::where('idCompany', KeyUtil::idCompany())->withCount('preguntas')->find($e->idFormularioInterno)
            : null;
        return [
            'id' => $e->idEvento,
            'nombre' => $e->nombre,
            'fechaInicial' => $e->fechaInicial,
            'hora' => $e->hora,
            'hora_final' => $e->hora_final,
            'estado' => $e->estado,
            'tipoEvento' => $e->tipoEvento,
            'formProvider' => $e->formProvider,
            'formUrl' => $form ? null : $e->formUrl,
            'formulario' => $form ? [
                'id' => $form->id, 'titulo' => $form->titulo, 'estado' => $form->estado, 'slug' => $form->slug,
                'preguntas' => $form->preguntas_count,
            ] : null,
            // Inscritos = respuestas del formulario vinculado (lo mismo que cuenta la pantalla del evento).
            'inscritos' => $form ? DB::table('formulario_respuestas')->where('idFormulario', $form->id)->count() : null,
        ];
    }

    /**
     * Vincula (o desvincula con idFormulario = null) el formulario interno de
     * inscripción de un evento. Mismo efecto que el asistente de integración del
     * front (FormIntegrationWizard): idFormularioInterno + formProvider 'interno'.
     * Evento y formulario tienen que ser de la empresa del token. No publica nada.
     */
    public function vincularFormulario(Request $request, $id): JsonResponse
    {
        if (!$this->puede('vincular_formulario')) {
            return $this->negado('vincular_formulario', 'No autorizado para modificar eventos');
        }
        $e = $this->eventoDeEmpresa($id);
        if (!$e) {
            return $this->noExiste('evento');
        }
        $request->validate(['idFormulario' => 'present|nullable|integer']);
        $idForm = $request->input('idFormulario');
        if ($idForm !== null) {
            if (!$this->puede('formularios')) {
                return $this->negado('formularios', 'No autorizado para consultar formularios');
            }
            if (!$this->formularioDeEmpresa($idForm)) {
                return $this->noExiste('formulario');
            }
        }
        $e->update($idForm !== null
            ? ['idFormularioInterno' => (int) $idForm, 'formProvider' => 'interno', 'formUrl' => '', 'linkRegistro' => '']
            : ['idFormularioInterno' => null, 'formProvider' => null, 'formUrl' => null, 'linkRegistro' => null]);
        return response()->json($this->detalleEvento($e->fresh()));
    }
}
