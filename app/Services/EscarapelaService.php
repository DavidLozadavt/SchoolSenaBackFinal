<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Evento;
use App\Models\Formulario;
use App\Models\FormularioPregunta;
use App\Models\FormularioRespuesta;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Escarapela de quien responde un formulario: nombre, identificación y un QR
 * con un código único por persona. Cada formulario decide si la entrega al
 * registrarse y si el administrador puede generarlas desde el panel, una por
 * una o todas juntas (pestaña "Escarapelas").
 *
 * El QR solo lleva la URL de verificación con ese código; los datos personales
 * nunca viajan en el QR. El PDF se arma al pedirlo (no se guarda), así una
 * corrección en la respuesta sale en la escarapela sin regenerar archivos.
 */
class EscarapelaService
{
    /** Valores de `configuracion.campoEscarapela` en una pregunta. */
    public const CAMPO_NOMBRE = 'nombre';
    public const CAMPO_IDENTIFICACION = 'identificacion';

    /**
     * Si esa base ya tiene las columnas de la escarapela. Mientras no se
     * corran sus migraciones la función queda apagada.
     */
    public function disponibleEn(string $connection): bool
    {
        $esquema = Schema::connection($connection);

        return $esquema->hasColumn('formulario_respuestas', 'codigoEscarapela')
            && $esquema->hasColumn('formularios', 'escarapelaAlRegistrar')
            && $esquema->hasColumn('formularios', 'escarapelaDiseno');
    }

    public const MENSAJE = 'Presenta este código en el ingreso. Es personal e intransferible.';

    /**
     * Diseño de la escarapela del formulario: lo que el administrador
     * personalizó y, en lo demás, el aspecto por defecto. `titulo` y `fecha`
     * quedan en null mientras no se cambien: salen del evento o del formulario.
     *
     * @return array<string, mixed>
     */
    public function disenoDe(Formulario $formulario): array
    {
        $guardado = array_filter((array) $formulario->escarapelaDiseno, fn ($valor) => $valor !== null && $valor !== '');

        return $guardado + [
            'plantilla' => 'clasica',
            'colorPrimario' => $formulario->colorTema ?: '#6366f1',
            'colorFondo' => '#ffffff',
            'colorTexto' => '#18181b',
            'fondoImagen' => null,
            'logo' => null,
            'titulo' => null,
            'mensaje' => self::MENSAJE,
            'ubicacion' => null,
            'fecha' => null,
            // Foto que el inscrito subió en el formulario; `preguntaFoto` en
            // null deja que se detecte por el título de la pregunta.
            'mostrarFoto' => false,
            'preguntaFoto' => null,
            // Respuestas del formulario que se imprimen además del nombre y
            // la identificación: [['idPregunta' => 5, 'etiqueta' => 'Cargo'], ...]
            'campos' => [],
        ];
    }

    /**
     * Lo que la escarapela toma del evento y de la empresa cuando el diseño
     * no lo cambia. Sin evento, el título es el del formulario.
     *
     * @return array{titulo: ?string, fecha: mixed, fechaFinal: mixed, hora: mixed, tipoEvento: mixed, razonSocial: ?string, logoUrl: ?string}
     */
    public function baseDe(string $connection, Formulario $formulario): array
    {
        $evento = $this->eventoDe($connection, $formulario->id);
        $empresa = Company::on($connection)->find($formulario->idCompany);

        return [
            'titulo' => $evento->nombre ?? $formulario->titulo,
            'fecha' => $evento->fechaInicial ?? null,
            'fechaFinal' => $evento->fechaFinal ?? null,
            'hora' => $evento->hora ?? null,
            'tipoEvento' => $evento->tipoEvento ?? null,
            'razonSocial' => $empresa->razonSocial ?? null,
            'logoUrl' => $empresa->rutaLogoUrl ?? null,
        ];
    }

    /**
     * Si quien se registra recibe su escarapela. Sin configurar (null) vale
     * la regla original: solo los formularios vinculados a un evento.
     */
    public function seEntregaAlRegistrar(string $connection, Formulario $formulario): bool
    {
        return $formulario->escarapelaAlRegistrar ?? (bool) $this->eventoDe($connection, $formulario->id);
    }

    /**
     * Código para una respuesta nueva, o null si el formulario no entrega
     * escarapela al registrarse.
     */
    public function codigoPara(string $connection, Formulario $formulario): ?string
    {
        if (!$this->seEntregaAlRegistrar($connection, $formulario)) {
            return null;
        }

        if (!Schema::connection($connection)->hasColumn('formulario_respuestas', 'codigoEscarapela')) {
            return null;
        }

        return $this->nuevoCodigo();
    }

    private function nuevoCodigo(): string
    {
        return Str::lower(Str::random(32));
    }

    /**
     * Datos de la escarapela, o null si el código no existe.
     *
     * @return array<string, mixed>|null
     */
    public function datos(string $codigo): ?array
    {
        $respuesta = FormularioRespuesta::where('codigoEscarapela', $codigo)->first();
        $formulario = $respuesta ? Formulario::with('preguntas')->find($respuesta->idFormulario) : null;

        if (!$formulario) {
            return null;
        }

        return $this->datosDe('mysql', $formulario, [$respuesta])[0] + $this->imagenesDe($formulario);
    }

    /**
     * Logo y fondo del diseño. Van aparte de `datosDe` para no repetirlos por
     * cada inscrito en la descarga masiva.
     *
     * @return array{logo: ?string, fondoImagen: ?string}
     */
    public function imagenesDe(Formulario $formulario): array
    {
        $diseno = $this->disenoDe($formulario);

        return ['logo' => $diseno['logo'], 'fondoImagen' => $diseno['fondoImagen']];
    }

    /**
     * Datos de la escarapela de cada respuesta de un formulario (ya cargado
     * con sus preguntas). A las que aún no tienen código se les asigna: son
     * las registradas antes de habilitar la escarapela en el formulario.
     *
     * @param  iterable<FormularioRespuesta>  $respuestas
     * @return array<int, array<string, mixed>>
     */
    public function datosDe(string $connection, Formulario $formulario, iterable $respuestas): array
    {
        $base = $this->baseDe($connection, $formulario);
        $diseno = $this->disenoDe($formulario);

        $escarapelas = [];
        foreach ($respuestas as $respuesta) {
            if (!$respuesta->codigoEscarapela) {
                $respuesta->codigoEscarapela = $this->nuevoCodigo();
                $respuesta->save();
            }

            $escarapelas[] = [
                'codigo' => $respuesta->codigoEscarapela,
                'nombre' => $this->valorDe($formulario, $respuesta, self::CAMPO_NOMBRE),
                'identificacion' => $this->valorDe($formulario, $respuesta, self::CAMPO_IDENTIFICACION),
                // El título y la fecha del diseño mandan sobre los del evento.
                'evento' => [
                    'nombre' => $diseno['titulo'] ?? $base['titulo'],
                    'fechaInicial' => $diseno['fecha'] ?? $base['fecha'],
                    'fechaFinal' => $base['fechaFinal'],
                    'hora' => $base['hora'],
                    'tipoEvento' => $base['tipoEvento'],
                ],
                'empresa' => [
                    'razonSocial' => $base['razonSocial'],
                    'logoUrl' => $base['logoUrl'],
                ],
                'plantilla' => $diseno['plantilla'],
                'color' => $diseno['colorPrimario'],
                'colorFondo' => $diseno['colorFondo'],
                'colorTexto' => $diseno['colorTexto'],
                'mensaje' => $diseno['mensaje'],
                'ubicacion' => $diseno['ubicacion'],
                'fotoUrl' => $diseno['mostrarFoto'] && $this->rutaFoto($formulario, $respuesta, $diseno)
                    ? url("api/escarapela/{$respuesta->codigoEscarapela}/foto")
                    : null,
                'campos' => $this->camposDe($formulario, $respuesta, $diseno),
                'registradoEn' => optional($respuesta->created_at)->toIso8601String(),
                'urlVerificacion' => $this->urlVerificacion($respuesta->codigoEscarapela),
            ];
        }

        return $escarapelas;
    }

    /** PDF de 10 x 14 cm listo para imprimir o mostrar en el celular. */
    public function pdf(array $datos)
    {
        return $this->pdfDe([$datos]);
    }

    /**
     * Un solo PDF con una escarapela por página.
     *
     * @param  array<int, array<string, mixed>>  $escarapelas
     */
    public function pdfDe(array $escarapelas)
    {
        $pdf = Pdf::loadView('escarapelas.escarapela', ['escarapelas' => $escarapelas, 'cajaQr' => self::CAJA_QR, 'pagina' => self::PAGINA])
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper([0, 0, self::PAGINA['ancho'], self::PAGINA['alto']]);

        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(function (int $numero, int $total, $lienzo) use ($escarapelas) {
            if (isset($escarapelas[$numero - 1])) {
                $this->dibujarQr($lienzo, $escarapelas[$numero - 1]['urlVerificacion']);
            }
        });

        return $pdf;
    }

    /** 10 x 14 cm en puntos (1 cm = 28.3465 pt). */
    public const PAGINA = ['ancho' => 283.46, 'alto' => 396.85];

    /** Recuadro del QR, en puntos: centrado y anclado al pie de la página. */
    public const CAJA_QR = ['lado' => 142.0, 'margenInferior' => 26.0];

    /**
     * Pinta el QR como rectángulos vectoriales sobre el lienzo del PDF.
     *
     * No se usa una imagen porque dompdf necesita gd o imagick para dibujar
     * cualquiera (SVG o PNG) y no todos los servidores los tienen. Los módulos
     * contiguos de una fila se unen en un solo rectángulo y cada uno se solapa
     * una fracción con sus vecinos: sin eso quedan líneas finas entre módulos
     * que impiden leer el código.
     */
    private function dibujarQr($lienzo, string $texto): void
    {
        $matriz = Encoder::encode($texto, ErrorCorrectionLevel::M())->getMatrix();
        $modulos = $matriz->getWidth();

        // Margen blanco de 4 módulos a cada lado, como pide el estándar.
        $margen = 4;
        $modulo = self::CAJA_QR['lado'] / ($modulos + 2 * $margen);
        $solape = 0.12;

        $izquierda = (self::PAGINA['ancho'] - self::CAJA_QR['lado']) / 2 + $margen * $modulo;
        $arriba = self::PAGINA['alto'] - self::CAJA_QR['margenInferior'] - self::CAJA_QR['lado'] + $margen * $modulo;

        for ($y = 0; $y < $modulos; $y++) {
            $x = 0;
            while ($x < $modulos) {
                if ($matriz->get($x, $y) !== 1) {
                    $x++;
                    continue;
                }

                $inicio = $x;
                while ($x < $modulos && $matriz->get($x, $y) === 1) {
                    $x++;
                }

                $lienzo->filled_rectangle(
                    $izquierda + $inicio * $modulo,
                    $arriba + $y * $modulo,
                    ($x - $inicio) * $modulo + $solape,
                    $modulo + $solape,
                    [0, 0, 0]
                );
            }
        }
    }

    /** Página pública del frontend que abre el QR. */
    public function urlVerificacion(string $codigo): string
    {
        $base = rtrim((string) (config('services.frontend.url') ?: config('app.url')), '/');

        return "{$base}/escarapela/{$codigo}";
    }

    /**
     * Nombre que la persona escribió en el formulario (misma detección que usa
     * la escarapela), para identificar respuestas enviadas sin sesión.
     */
    public function nombreEscrito(Formulario $formulario, FormularioRespuesta $respuesta): ?string
    {
        return $this->valorDe($formulario, $respuesta, self::CAMPO_NOMBRE);
    }

    /**
     * Preguntas cuya respuesta se puede agregar a la escarapela: todas menos
     * las de archivo y las del nombre y la identificación, que ya salen.
     *
     * @return array<int, array{id: int, titulo: string}>
     */
    public function preguntasImprimibles(Formulario $formulario): array
    {
        return $formulario->preguntas->where('tipo', '!=', 'archivo')
            ->whereNotIn('id', $this->idsYaImpresos($formulario))
            ->map(fn (FormularioPregunta $p) => ['id' => $p->id, 'titulo' => $p->titulo])
            ->values()
            ->all();
    }

    /**
     * Campos extra del diseño con lo que respondió el inscrito, en el orden
     * elegido. Se omiten los que dejó vacíos, las preguntas ya borradas y las
     * que resulten ser el nombre o la identificación (saldrían repetidas).
     *
     * @return array<int, array{etiqueta: ?string, valor: string}>
     */
    private function camposDe(Formulario $formulario, FormularioRespuesta $respuesta, array $diseno): array
    {
        $valores = collect((array) $respuesta->respuestas)->keyBy(fn ($r) => (int) ($r['idPregunta'] ?? 0));
        $yaImpresos = $this->idsYaImpresos($formulario);
        $campos = [];

        foreach ((array) $diseno['campos'] as $campo) {
            $pregunta = $formulario->preguntas->firstWhere('id', (int) ($campo['idPregunta'] ?? 0));
            $sirve = $pregunta && $pregunta->tipo !== 'archivo' && !in_array($pregunta->id, $yaImpresos, true);
            $valor = $sirve ? ($valores[$pregunta->id]['valor'] ?? null) : null;

            // Casillas: varias opciones marcadas.
            $texto = is_array($valor)
                ? implode(', ', array_filter($valor, 'is_scalar'))
                : (is_scalar($valor) ? trim((string) $valor) : '');

            if ($texto !== '') {
                $campos[] = ['etiqueta' => ($campo['etiqueta'] ?? null) ?: null, 'valor' => $texto];
            }
        }

        return $campos;
    }

    /**
     * Preguntas "Subir archivo" del formulario: de una de ellas sale la foto.
     *
     * @return array<int, array{id: int, titulo: string}>
     */
    public function preguntasDeArchivo(Formulario $formulario): array
    {
        return $formulario->preguntas->where('tipo', 'archivo')
            ->map(fn (FormularioPregunta $p) => ['id' => $p->id, 'titulo' => $p->titulo])
            ->values()
            ->all();
    }

    /** Foto del inscrito de un código, o null si su formulario no la muestra o no subió ninguna. */
    public function fotoDe(string $codigo): ?string
    {
        $respuesta = FormularioRespuesta::where('codigoEscarapela', $codigo)->first();
        $formulario = $respuesta ? Formulario::with('preguntas')->find($respuesta->idFormulario) : null;
        $diseno = $formulario ? $this->disenoDe($formulario) : null;

        if (!$diseno || !$diseno['mostrarFoto']) {
            return null;
        }

        $ruta = $this->rutaFoto($formulario, $respuesta, $diseno);

        return $ruta ? Storage::disk('public')->path($ruta) : null;
    }

    /**
     * Ruta (en el disco público) de la imagen que el inscrito subió en la
     * pregunta de foto: la elegida en el diseño o, si no, la primera pregunta
     * "Subir archivo" cuyo título hable de una foto. Solo sirve un adjunto de
     * formularios que sea imagen y exista: el valor lo escribió el inscrito.
     */
    private function rutaFoto(Formulario $formulario, FormularioRespuesta $respuesta, array $diseno): ?string
    {
        $archivos = $formulario->preguntas->where('tipo', 'archivo');

        $pregunta = $diseno['preguntaFoto']
            ? $archivos->firstWhere('id', (int) $diseno['preguntaFoto'])
            : $archivos->first(fn (FormularioPregunta $p) => Str::contains(Str::lower(Str::ascii($p->titulo)), self::ES_FOTO));

        if (!$pregunta) {
            return null;
        }

        foreach ((array) $respuesta->respuestas as $r) {
            if ((int) ($r['idPregunta'] ?? 0) !== (int) $pregunta->id || !is_string($r['valor'] ?? null)) {
                continue;
            }

            // Varios adjuntos van separados por coma: sirve la primera imagen.
            foreach (explode(',', $r['valor']) as $url) {
                if (preg_match('#/storage/(formularios/adjuntos/[A-Za-z0-9_-]+\.(?:jpe?g|png))$#i', trim($url), $partes)
                    && Storage::disk('public')->exists($partes[1])) {
                    return $partes[1];
                }
            }
        }

        return null;
    }

    /** Títulos de preguntas "Subir archivo" que piden la foto del inscrito. */
    private const ES_FOTO = ['foto', 'selfie', 'retrato', 'imagen de perfil', 'avatar'];

    public function identificacionEscrita(Formulario $formulario, FormularioRespuesta $respuesta): ?string
    {
        return $this->valorDe($formulario, $respuesta, self::CAMPO_IDENTIFICACION);
    }

    private function eventoDe(string $connection, int $idFormulario): ?Evento
    {
        return Evento::on($connection)->where('idFormularioInterno', $idFormulario)->first();
    }

    /**
     * Pregunta de la que sale el nombre o la identificación: la marcada para
     * la escarapela; si ninguna está marcada, se deduce por el título
     * ("Nombre completo", "Usuario", "Cédula"...). Para el nombre, si ningún
     * título coincide se toma la primera respuesta corta de texto que no sea
     * otro dato reconocible (identificación, correo, teléfono...).
     */
    private function preguntaDe(Formulario $formulario, string $campo): ?FormularioPregunta
    {
        $sinMarcar = $formulario->preguntas->filter(fn (FormularioPregunta $p) => empty($p->configuracion['campoEscarapela']));

        $pregunta = $formulario->preguntas->first(fn (FormularioPregunta $p) => ($p->configuracion['campoEscarapela'] ?? null) === $campo)
            ?? $this->porTitulo($sinMarcar, $campo);

        if (!$pregunta && $campo === self::CAMPO_NOMBRE) {
            $pregunta = $sinMarcar->first(fn (FormularioPregunta $p) => $p->tipo === 'texto_corto'
                && empty($p->configuracion['soloNumeros'])
                && !$this->pareceSer($p->titulo, self::CAMPO_IDENTIFICACION)
                && !Str::contains(Str::lower(Str::ascii($p->titulo)), self::NO_ES_NOMBRE));
        }

        return $pregunta;
    }

    /**
     * Ids de las preguntas del nombre y la identificación: la escarapela ya
     * las imprime, no pueden repetirse como campo extra.
     *
     * @return array<int, int>
     */
    private function idsYaImpresos(Formulario $formulario): array
    {
        return array_values(array_filter([
            $this->preguntaDe($formulario, self::CAMPO_NOMBRE)?->id,
            $this->preguntaDe($formulario, self::CAMPO_IDENTIFICACION)?->id,
        ]));
    }

    /** Respuesta del inscrito a la pregunta del nombre o de la identificación. */
    private function valorDe(Formulario $formulario, FormularioRespuesta $respuesta, string $campo): ?string
    {
        $pregunta = $this->preguntaDe($formulario, $campo);

        if (!$pregunta) {
            return null;
        }

        foreach ((array) $respuesta->respuestas as $r) {
            if ((int) ($r['idPregunta'] ?? 0) === (int) $pregunta->id && is_scalar($r['valor'] ?? null)) {
                $valor = trim((string) $r['valor']);

                return $valor !== '' ? $valor : null;
            }
        }

        return null;
    }

    /**
     * Palabras que delatan cada dato en el título de una pregunta, de la más a
     * la menos fiable: con "Usuario" y "Nombre completo" en el mismo formulario
     * gana "Nombre completo", sin importar el orden de las preguntas.
     */
    private const PALABRAS = [
        self::CAMPO_NOMBRE => ['nombre', 'asistente', 'participante', 'inscrito', 'usuario'],
        self::CAMPO_IDENTIFICACION => ['identificacion', 'cedula', 'documento', 'numero de id'],
    ];

    private function porTitulo($preguntas, string $campo): ?FormularioPregunta
    {
        foreach (self::PALABRAS[$campo] as $palabra) {
            $pregunta = $preguntas->first(fn (FormularioPregunta $p) => Str::contains(Str::lower(Str::ascii($p->titulo)), $palabra));

            if ($pregunta) {
                return $pregunta;
            }
        }

        return null;
    }

    private function pareceSer(string $titulo, string $campo): bool
    {
        return Str::contains(Str::lower(Str::ascii($titulo)), self::PALABRAS[$campo]);
    }

    /** Títulos de respuestas cortas que no pueden ser el nombre del asistente. */
    private const NO_ES_NOMBRE = ['correo', 'email', 'e-mail', 'mail', 'telefono', 'celular', 'whatsapp', 'direccion', 'ciudad', 'edad', 'empresa', 'cargo'];
}
