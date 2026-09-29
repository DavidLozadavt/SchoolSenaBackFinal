<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;

class ValidacionCuestionario
{
    public static function crear(array $datos): LaravelValidator
    {
        $preguntas = is_array($datos['preguntas'] ?? null) ? $datos['preguntas'] : [];
        $reglas = [
            'titulo' => 'required|string|max:500',
            'clasificacion' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'idMateria' => 'required|exists:materia,id',
            'preguntas' => 'required|array|min:1',
            'preguntas.*' => 'array',
            'preguntas.*.id' => 'nullable|integer',
            'preguntas.*.tipo' => 'required|in:Párrafo,Varias opciones',
            'preguntas.*.titulo' => 'required|string|max:1000',
            'preguntas.*.explicacionRespuesta' => 'nullable|string',
            'preguntasMinimasAprobar' => 'nullable|integer|min:1|max:' . max(1, count($preguntas)),
            'intervaloReintento' => 'nullable|integer|min:1|max:525600',
        ];
        $mensajes = [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe contener texto.',
            'array' => 'Revisa el contenido de :attribute.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'boolean' => 'Selecciona un valor válido para :attribute.',
            'in' => 'Selecciona un valor válido para :attribute.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'min.numeric' => 'El campo :attribute debe ser al menos :min.',
            'max.numeric' => 'El campo :attribute no puede superar :max.',
            'titulo.required' => 'El título del cuestionario es obligatorio.',
            'idMateria.required' => 'Selecciona el RAP del cuestionario.',
            'idMateria.exists' => 'Selecciona un RAP válido para el cuestionario.',
            'preguntas.required' => 'Añade al menos una pregunta al cuestionario.',
            'preguntas.min' => 'Añade al menos una pregunta al cuestionario.',
        ];
        $atributos = [
            'titulo' => 'título del cuestionario',
            'clasificacion' => 'clasificación',
            'descripcion' => 'descripción del cuestionario',
            'idMateria' => 'RAP',
            'preguntas' => 'preguntas',
            'preguntasMinimasAprobar' => 'preguntas mínimas para aprobar',
            'intervaloReintento' => 'intervalo entre intentos',
        ];
        foreach ($preguntas as $i => $pregunta) {
            $n = (int) $i + 1;
            $base = "preguntas.{$i}";
            $atributos[$base] = "pregunta {$n}";
            foreach (['id' => 'identificador', 'tipo' => 'tipo', 'titulo' => 'título', 'explicacionRespuesta' => 'explicación de la respuesta'] as $campo => $nombre) {
                $atributos["{$base}.{$campo}"] = "{$nombre} de la pregunta {$n}";
            }
            $mensajes["{$base}.titulo.required"] = "El título de la pregunta {$n} es obligatorio.";
            if (!is_array($pregunta) || ($pregunta['tipo'] ?? '') !== 'Varias opciones') {
                continue;
            }
            $reglas["{$base}.opciones"] = 'required|array|min:2';
            $reglas["{$base}.opciones.*"] = 'array';
            $reglas["{$base}.opciones.*.id"] = 'nullable|integer';
            $reglas["{$base}.opciones.*.texto"] = 'required|string';
            $reglas["{$base}.opciones.*.esCorrecta"] = 'nullable|boolean';
            $atributos["{$base}.opciones"] = "opciones de la pregunta {$n}";
            $mensajes["{$base}.opciones.required"] = "Añade al menos dos opciones a la pregunta {$n}.";
            $mensajes["{$base}.opciones.min"] = "Añade al menos dos opciones a la pregunta {$n}.";
            foreach (is_array($pregunta['opciones'] ?? null) ? $pregunta['opciones'] : [] as $j => $opcion) {
                $o = (int) $j + 1;
                foreach (['id', 'texto', 'esCorrecta'] as $campo) {
                    $atributos["{$base}.opciones.{$j}.{$campo}"] = "{$campo} de la opción {$o} de la pregunta {$n}";
                }
                $mensajes["{$base}.opciones.{$j}.texto.required"] = "El texto de la opción {$o} de la pregunta {$n} es obligatorio.";
            }
        }

        $validator = Validator::make($datos, $reglas, $mensajes, $atributos);
        $validator->after(function (LaravelValidator $validator) use ($preguntas) {
            foreach ($preguntas as $i => $pregunta) {
                if (!is_array($pregunta) || ($pregunta['tipo'] ?? '') !== 'Varias opciones') {
                    continue;
                }
                $opciones = is_array($pregunta['opciones'] ?? null) ? $pregunta['opciones'] : [];
                $correctas = count(array_filter($opciones, fn ($opcion) => is_array($opcion)
                    && in_array($opcion['esCorrecta'] ?? false, [true, 1, '1'], true)));
                $n = (int) $i + 1;
                if ($correctas !== 1) {
                    $validator->errors()->add("preguntas.{$i}.respuestaCorrecta", "Selecciona una respuesta correcta para la pregunta {$n}.");
                }
            }
        });

        return $validator;
    }
}
