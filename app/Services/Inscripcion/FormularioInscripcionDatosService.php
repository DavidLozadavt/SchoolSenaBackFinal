<?php

namespace App\Services\Inscripcion;

use App\Models\Formulario;
use App\Models\FormularioPregunta;
use App\Models\FormularioRespuesta;
use App\Models\Proceso;
use App\Models\Tercero;
use Illuminate\Support\Str;

class FormularioInscripcionDatosService
{
    public const SLUG_INSCRIPCION = 'inscripcion-estudiantes';

    public function obtenerFormularioInscripcion(): ?Formulario
    {
        return Formulario::with('preguntas')->where('slug', self::SLUG_INSCRIPCION)->first();
    }

    /**
     * Busca la respuesta más reciente del formulario de inscripción por documento del estudiante.
     */
    public function buscarRespuestaPorDocumento(string $documento, ?int $idCompany = null): ?FormularioRespuesta
    {
        $documento = trim($documento);
        if ($documento === '') {
            return null;
        }

        $formulario = $this->obtenerFormularioInscripcion();
        if (!$formulario) {
            return null;
        }

        $preguntaDocumento = $this->resolverPreguntaPorPatron($formulario, ['numero de documento del estudiante', 'documento del estudiante']);
        if (!$preguntaDocumento) {
            return null;
        }

        $query = FormularioRespuesta::where('idFormulario', $formulario->id)
            ->orderByDesc('id');

        if ($idCompany) {
            $query->whereHas('formulario', fn ($q) => $q->where('idCompany', $idCompany));
        }

        return $query->get()->first(function (FormularioRespuesta $r) use ($preguntaDocumento, $documento) {
            $valor = $this->valorRespuesta($r, (int) $preguntaDocumento->id);

            return $valor !== null && trim((string) $valor) === $documento;
        });
    }

    public function buscarRespuestaPorId(int $id): ?FormularioRespuesta
    {
        return FormularioRespuesta::with('formulario.preguntas')->find($id);
    }

    /**
     * Convierte el JSON de respuestas en bloques estudiante / tutor / documentos.
     */
    public function formatearDatosFormulario(FormularioRespuesta $respuesta): array
    {
        $formulario = $respuesta->formulario ?? Formulario::with('preguntas')->find($respuesta->idFormulario);
        $preguntas = $formulario?->preguntas ?? collect();

        $estudiante = [
            'nombreCompleto' => $this->valorPorTitulo($respuesta, $preguntas, ['nombre completo del estudiante']),
            'tipoDocumento' => $this->valorPorTitulo($respuesta, $preguntas, ['tipo de documento del estudiante']),
            'documento' => $this->valorPorTitulo($respuesta, $preguntas, ['numero de documento del estudiante', 'documento del estudiante']),
            'fechaNacimiento' => $this->valorPorTitulo($respuesta, $preguntas, ['fecha de nacimiento del estudiante']),
            'email' => $this->valorPorTitulo($respuesta, $preguntas, ['correo electronico del estudiante', 'correo electrónico del estudiante']),
            'telefono' => $this->valorPorTitulo($respuesta, $preguntas, ['telefono del estudiante', 'teléfono del estudiante']),
            'programaInteres' => $this->valorPorTitulo($respuesta, $preguntas, ['programa de interes', 'programa de interés']),
            'jornada' => $this->valorPorTitulo($respuesta, $preguntas, ['jornada']),
        ];

        $tutor = [
            'nombreCompleto' => $this->valorPorTitulo($respuesta, $preguntas, ['nombre completo del tutor', 'nombre completo del acudiente']),
            'parentesco' => $this->valorPorTitulo($respuesta, $preguntas, ['parentesco del tutor']),
            'documento' => $this->valorPorTitulo($respuesta, $preguntas, ['identificacion del tutor', 'identificación del tutor']),
            'telefono' => $this->valorPorTitulo($respuesta, $preguntas, ['telefono del tutor', 'teléfono del tutor']),
            'email' => $this->valorPorTitulo($respuesta, $preguntas, ['correo del tutor']),
        ];

        $documentos = [];
        foreach ($preguntas as $pregunta) {
            $titulo = Str::lower((string) $pregunta->titulo);
            $esDocumento = Str::contains($titulo, ['certificado', 'documento', 'archivo', 'soporte'])
                || Str::contains(Str::lower((string) $pregunta->descripcion), ['suba', 'cargue', 'pdf', 'imagen']);

            if (!$esDocumento) {
                continue;
            }

            $valor = $this->valorRespuesta($respuesta, (int) $pregunta->id);
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }

            foreach ($this->extraerUrls($valor) as $url) {
                $documentos[] = [
                    'titulo' => $pregunta->titulo,
                    'url' => $url,
                ];
            }
        }

        return [
            'idFormularioRespuesta' => (int) $respuesta->id,
            'fechaEnvio' => $respuesta->created_at?->toIso8601String(),
            'estudiante' => $estudiante,
            'tutor' => $tutor,
            'documentos' => $documentos,
            'respuestasCrudas' => collect($respuesta->respuestas ?? [])->map(function ($item) use ($preguntas) {
                $idPregunta = (int) ($item['idPregunta'] ?? 0);
                $pregunta = $preguntas->firstWhere('id', $idPregunta);

                return [
                    'idPregunta' => $idPregunta,
                    'titulo' => $pregunta?->titulo,
                    'valor' => $item['valor'] ?? null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Crea o actualiza el tercero del estudiante a partir del formulario enviado.
     */
    public function sincronizarTerceroDesdeRespuesta(FormularioRespuesta $respuesta, int $idCompany = 1): ?Tercero
    {
        $datos = $this->formatearDatosFormulario($respuesta);
        $est = $datos['estudiante'];
        $documento = trim((string) ($est['documento'] ?? ''));
        $nombre = trim((string) ($est['nombreCompleto'] ?? ''));

        if ($documento === '' || $nombre === '') {
            return null;
        }

        $tercero = Tercero::where('identificacion', $documento)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->first();

        if (!$tercero) {
            $tercero = new Tercero();
            $tercero->identificacion = $documento;
            $tercero->idCompany = $idCompany;
        }

        $tercero->nombre = $nombre;
        if (!empty($est['email'])) {
            $tercero->email = $est['email'];
        }
        if (!empty($est['telefono'])) {
            $tercero->telefono = $est['telefono'];
        }
        $tercero->save();

        return $tercero;
    }

    public function resolverParaFactura(?Tercero $tercero, int $idCompany, ?int $idFormularioRespuesta = null): ?array
    {
        if ($idFormularioRespuesta) {
            $respuesta = $this->buscarRespuestaPorId($idFormularioRespuesta);
            if ($respuesta) {
                return $this->formatearDatosFormulario($respuesta);
            }
        }

        $documento = trim((string) ($tercero?->identificacion ?? ''));
        if ($documento === '') {
            return null;
        }

        $respuesta = $this->buscarRespuestaPorDocumento($documento, $idCompany);

        return $respuesta ? $this->formatearDatosFormulario($respuesta) : null;
    }

    public function resolverIdProcesoDesdeRespuesta(FormularioRespuesta $respuesta): ?int
    {
        $datos = $this->formatearDatosFormulario($respuesta);
        $programa = trim((string) ($datos['estudiante']['programaInteres'] ?? ''));
        if ($programa === '') {
            return null;
        }

        if (ctype_digit($programa)) {
            $proceso = Proceso::find((int) $programa);

            return $proceso?->id;
        }

        $proceso = Proceso::where('nombreProceso', $programa)
            ->orWhere('nombreProceso', 'like', '%' . $programa . '%')
            ->first();

        return $proceso?->id;
    }

    public function resolverNombreProgramaDesdeRespuesta(FormularioRespuesta $respuesta): ?string
    {
        $datos = $this->formatearDatosFormulario($respuesta);
        $programa = trim((string) ($datos['estudiante']['programaInteres'] ?? ''));

        if ($programa === '') {
            return null;
        }

        $idProceso = $this->resolverIdProcesoDesdeRespuesta($respuesta);
        if ($idProceso) {
            return Proceso::find($idProceso)?->nombreProceso ?? $programa;
        }

        return $programa;
    }

    private function valorPorTitulo(FormularioRespuesta $respuesta, $preguntas, array $patrones): ?string
    {
        $pregunta = $this->resolverPreguntaPorPatron($respuesta->formulario ?? null, $patrones, $preguntas);
        if (!$pregunta) {
            return null;
        }

        $valor = $this->valorRespuesta($respuesta, (int) $pregunta->id);

        return $valor !== null ? trim((string) $valor) : null;
    }

    private function resolverPreguntaPorPatron(?Formulario $formulario, array $patrones, $preguntas = null): ?FormularioPregunta
    {
        $preguntas = $preguntas ?? ($formulario?->preguntas ?? collect());

        foreach ($preguntas as $pregunta) {
            $titulo = Str::lower((string) $pregunta->titulo);
            foreach ($patrones as $patron) {
                if (Str::contains($titulo, Str::lower($patron))) {
                    return $pregunta;
                }
            }
        }

        return null;
    }

    private function valorRespuesta(FormularioRespuesta $respuesta, int $idPregunta)
    {
        foreach ($respuesta->respuestas ?? [] as $item) {
            if ((int) ($item['idPregunta'] ?? 0) === $idPregunta) {
                return $item['valor'] ?? null;
            }
        }

        return null;
    }

    private function extraerUrls($valor): array
    {
        if (is_array($valor)) {
            return array_values(array_filter(array_map('strval', $valor)));
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return [];
        }

        if (str_contains($texto, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $texto))));
        }

        return [$texto];
    }
}
