<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Formulario;
use App\Models\FormularioRespuesta;
use App\Services\EscarapelaService;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Escarapelas de los formularios.
 *
 * `show` y `pdf` son públicas y no piden sesión: quien se inscribe por el
 * formulario público no tiene cuenta; el acceso lo da el código aleatorio de
 * 32 caracteres que solo recibe esa persona. El resto es del panel (pestaña
 * "Escarapelas") y exige sesión.
 */
class EscarapelaController extends Controller
{
    public function __construct(private EscarapelaService $escarapelas)
    {
    }

    /** Datos para la vista previa y para la página que abre el QR. */
    public function show(string $codigo)
    {
        $datos = $this->escarapelas->datos($codigo);

        if (!$datos) {
            return response()->json(['error' => 'Escarapela no encontrada.'], 404);
        }

        return response()->json($datos);
    }

    public function pdf(string $codigo)
    {
        $datos = $this->escarapelas->datos($codigo);

        if (!$datos) {
            return response()->json(['error' => 'Escarapela no encontrada.'], 404);
        }

        $archivo = 'escarapela-' . Str::slug($datos['evento']['nombre'] ?: 'evento') . '.pdf';

        return $this->escarapelas->pdf($datos)->download($archivo);
    }

    /** Foto que el inscrito subió en el formulario, para su escarapela. */
    public function foto(string $codigo)
    {
        $archivo = $this->escarapelas->fotoDe($codigo);

        if (!$archivo) {
            return response()->json(['error' => 'Foto no encontrada.'], 404);
        }

        return response()->file($archivo, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Formularios de la empresa con lo que cada uno tiene habilitado. */
    public function formularios()
    {
        [$connection, $idCompany] = $this->empresaEnSesion();

        $formularios =Formulario::on($connection)->where('idCompany', $idCompany)
            ->withCount('respuestas')
            ->orderBy('created_at', 'desc')
            ->get();

        $eventos = Evento::on($connection)->whereIn('idFormularioInterno', $formularios->pluck('id'))
            ->pluck('nombre', 'idFormularioInterno');

        return response()->json([
            'disponible' => $this->escarapelas->disponibleEn($connection),
            'formularios' => $formularios->map(fn (Formulario $f) => [
                'id' => $f->id,
                'titulo' => $f->titulo,
                'estado' => $f->estado,
                'colorTema' => $f->colorTema,
                'respuestas_count' => $f->respuestas_count,
                'evento' => $eventos[$f->id] ?? null,
                'alRegistrar' => $f->escarapelaAlRegistrar ?? isset($eventos[$f->id]),
                'individual' => (bool) $f->escarapelaIndividual,
                'masiva' => (bool) $f->escarapelaMasiva,
            ]),
        ]);
    }

    /** Imágenes del diseño: data URL ya reducida por el frontend. */
    private const IMAGEN = '/^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+\/=]+$/';
    private const COLOR = '/^#[0-9a-fA-F]{6}$/';

    /** Diseño actual de la escarapela y lo que hereda del evento, para el editor. */
    public function diseno($id)
    {
        [$connection, $formulario] = $this->formulario($id);

        return response()->json([
            'diseno' => $this->escarapelas->disenoDe($formulario),
            'base' => $this->escarapelas->baseDe($connection, $formulario),
            'preguntasArchivo' => $this->escarapelas->preguntasDeArchivo($formulario),
            'preguntas' => $this->escarapelas->preguntasImprimibles($formulario),
        ]);
    }

    /** Habilita o apaga cada modo de escarapela en un formulario y guarda su diseño. */
    public function configurar(Request $request, $id)
    {
        $cambios = $request->validate([
            'alRegistrar' => 'sometimes|boolean',
            'individual' => 'sometimes|boolean',
            'masiva' => 'sometimes|boolean',
            'diseno' => 'sometimes|array',
            'diseno.plantilla' => 'sometimes|in:clasica,moderna',
            'diseno.colorPrimario' => ['sometimes', 'regex:' . self::COLOR],
            'diseno.colorFondo' => ['sometimes', 'regex:' . self::COLOR],
            'diseno.colorTexto' => ['sometimes', 'regex:' . self::COLOR],
            // Entre las dos deben caber en un solo paquete de MySQL (1 MB por defecto).
            'diseno.fondoImagen' => ['nullable', 'string', 'max:600000', 'regex:' . self::IMAGEN],
            'diseno.logo' => ['nullable', 'string', 'max:300000', 'regex:' . self::IMAGEN],
            'diseno.titulo' => 'nullable|string|max:120',
            'diseno.mensaje' => 'nullable|string|max:160',
            'diseno.ubicacion' => 'nullable|string|max:120',
            'diseno.fecha' => 'nullable|date_format:Y-m-d',
            'diseno.mostrarFoto' => 'sometimes|boolean',
            'diseno.preguntaFoto' => 'nullable|integer',
            'diseno.campos' => 'sometimes|array|max:3',
            'diseno.campos.*.idPregunta' => 'required|integer',
            'diseno.campos.*.etiqueta' => 'nullable|string|max:40',
        ]);

        [$connection, $formulario] = $this->formulario($id);

        if (!$this->escarapelas->disponibleEn($connection)) {
            return response()->json(['error' => 'Las escarapelas aún no están disponibles para esta empresa.'], 422);
        }

        $columnas = ['alRegistrar' => 'escarapelaAlRegistrar', 'individual' => 'escarapelaIndividual', 'masiva' => 'escarapelaMasiva'];
        foreach (array_intersect_key($cambios, $columnas) as $modo => $valor) {
            $formulario->{$columnas[$modo]} = (bool) $valor;
        }
        if (isset($cambios['diseno'])) {
            $formulario->escarapelaDiseno = $cambios['diseno'];
        }
        $formulario->save();

        return response()->json([
            'alRegistrar' => $this->escarapelas->seEntregaAlRegistrar($connection, $formulario),
            'individual' => (bool) $formulario->escarapelaIndividual,
            'masiva' => (bool) $formulario->escarapelaMasiva,
        ]);
    }

    /**
     * Datos para dibujar las escarapelas en el navegador con el diseño del
     * formulario: la de un inscrito (`?respuesta=`) o las de todos.
     */
    public function datosInscritos(Request $request, $id)
    {
        [$connection, $formulario] = $this->formulario($id);

        $idRespuesta = $request->query('respuesta');

        if ($idRespuesta ? !$formulario->escarapelaIndividual : !$formulario->escarapelaMasiva) {
            return response()->json(['error' => 'Este formulario no tiene habilitada esa forma de generar escarapelas.'], 403);
        }

        $respuestas = FormularioRespuesta::on($connection)->where('idFormulario', $formulario->id)
            ->when($idRespuesta, fn ($q) => $q->where('id', $idRespuesta))
            ->orderBy('id')
            ->get();

        if ($respuestas->isEmpty()) {
            return response()->json(['error' => $idRespuesta ? 'Inscrito no encontrado.' : 'Este formulario todavía no tiene inscritos.'], $idRespuesta ? 404 : 422);
        }

        return response()->json($this->escarapelas->imagenesDe($formulario) + [
            'escarapelas' => $this->escarapelas->datosDe($connection, $formulario, $respuestas),
        ]);
    }

    /** Inscritos de un formulario, para generar su escarapela una por una. */
    public function inscritos($id)
    {
        [$connection, $formulario] = $this->formulario($id);

        if (!$formulario->escarapelaIndividual) {
            return response()->json(['error' => 'Este formulario no tiene habilitada la escarapela individual.'], 403);
        }

        $inscritos = FormularioRespuesta::on($connection)->where('idFormulario', $formulario->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (FormularioRespuesta $r) => [
                'id' => $r->id,
                'nombre' => $this->escarapelas->nombreEscrito($formulario, $r),
                'identificacion' => $this->escarapelas->identificacionEscrita($formulario, $r),
                'registradoEn' => optional($r->created_at)->toIso8601String(),
            ]);

        return response()->json($inscritos);
    }

    public function pdfInscrito($id, $idRespuesta)
    {
        [$connection, $formulario] = $this->formulario($id);

        if (!$formulario->escarapelaIndividual) {
            return response()->json(['error' => 'Este formulario no tiene habilitada la escarapela individual.'], 403);
        }

        $respuesta = FormularioRespuesta::on($connection)->where('idFormulario', $formulario->id)->findOrFail($idRespuesta);
        $datos = $this->escarapelas->datosDe($connection, $formulario, [$respuesta]);

        $archivo = 'escarapela-' . Str::slug($datos[0]['nombre'] ?: $formulario->titulo) . '.pdf';

        return $this->escarapelas->pdfDe($datos)->download($archivo);
    }

    /** Todas las escarapelas del formulario en un solo PDF, una por página. */
    public function pdfMasivo($id)
    {
        [$connection, $formulario] = $this->formulario($id);

        if (!$formulario->escarapelaMasiva) {
            return response()->json(['error' => 'Este formulario no tiene habilitadas las escarapelas masivas.'], 403);
        }

        $respuestas = FormularioRespuesta::on($connection)->where('idFormulario', $formulario->id)->orderBy('id')->get();

        if ($respuestas->isEmpty()) {
            return response()->json(['error' => 'Este formulario todavía no tiene inscritos.'], 422);
        }

        // Con cientos de inscritos, armar el PDF supera el límite por defecto.
        @set_time_limit(300);

        $archivo = 'escarapelas-' . Str::slug($formulario->titulo ?: 'formulario') . '.pdf';

        return $this->escarapelas->pdfDe($this->escarapelas->datosDe($connection, $formulario, $respuestas))->download($archivo);
    }

    /**
     * Base y empresa de la sesión. School solo tiene su propia base; la
     * conexión va explícita para que el servicio sea el mismo del ERP.
     *
     * @return array{0: string, 1: int}
     */
    private function empresaEnSesion(): array
    {
        return ['mysql', (int) KeyUtil::idCompany()];
    }

    /**
     * Formulario de la empresa en sesión, con sus preguntas.
     *
     * @return array{0: string, 1: Formulario}
     */
    private function formulario($id): array
    {
        [$connection, $idCompany] = $this->empresaEnSesion();

        $formulario = Formulario::on($connection)->with('preguntas')
            ->where('idCompany', $idCompany)
            ->findOrFail($id);

        return [$connection, $formulario];
    }
}
