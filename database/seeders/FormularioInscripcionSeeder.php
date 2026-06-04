<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Formulario;
use App\Models\FormularioPregunta;

class FormularioInscripcionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Crear el formulario base de Inscripción
        $formulario = Formulario::updateOrCreate(
            ['slug' => 'inscripcion-estudiantes'],
            [
                'idCompany' => 1,
                'idUser' => 1,
                'titulo' => 'Matrícula e Inscripción de Estudiantes',
                'descripcion' => 'Formulario oficial de inscripción pública para nuevos aspirantes y estudiantes de la institución.',
                'colorTema' => '#4f46e5', // Color índigo premium
                'estado' => 'publicado',
                'requiereAutenticacion' => false,
            ]
        );

        // 2. Definir las preguntas
        $preguntas = [
            [
                'titulo' => 'Nombre Completo del Estudiante',
                'tipo' => 'texto_corto',
                'descripcion' => 'Ingresa los dos nombres y dos apellidos del alumno.',
                'esObligatoria' => true,
                'orden' => 1,
            ],
            [
                'titulo' => 'Tipo de Documento del Estudiante',
                'tipo' => 'desplegable',
                'descripcion' => 'Selecciona el tipo de identificación oficial.',
                'esObligatoria' => true,
                'orden' => 2,
                'opciones' => ['Registro Civil', 'Tarjeta de Identidad', 'Cédula de Ciudadanía', 'Cédula de Extranjería']
            ],
            [
                'titulo' => 'Número de Documento del Estudiante',
                'tipo' => 'texto_corto',
                'descripcion' => 'Digita el número de identificación sin puntos ni guiones.',
                'esObligatoria' => true,
                'orden' => 3,
            ],
            [
                'titulo' => 'Fecha de Nacimiento del Estudiante',
                'tipo' => 'fecha',
                'descripcion' => 'Ingresa la fecha de nacimiento oficial.',
                'esObligatoria' => true,
                'orden' => 4,
            ],
            [
                'titulo' => 'Correo Electrónico del Estudiante',
                'tipo' => 'texto_corto',
                'descripcion' => 'Dirección de contacto institucional o personal.',
                'esObligatoria' => true,
                'orden' => 5,
            ],
            [
                'titulo' => 'Teléfono del Estudiante',
                'tipo' => 'texto_corto',
                'descripcion' => 'Número de teléfono celular.',
                'esObligatoria' => true,
                'orden' => 6,
            ],
            [
                'titulo' => 'Programa de Interés',
                'tipo' => 'texto_corto',
                'descripcion' => 'Indique el programa o proceso académico al que aspira inscribirse.',
                'esObligatoria' => true,
                'orden' => 7,
            ],
            [
                'titulo' => 'Nombre Completo del Tutor / Acudiente',
                'tipo' => 'texto_corto',
                'descripcion' => 'Nombre completo del representante legal responsable del alumno.',
                'esObligatoria' => true,
                'orden' => 8,
            ],
            [
                'titulo' => 'Parentesco del Tutor',
                'tipo' => 'desplegable',
                'descripcion' => 'Relación familiar del acudiente con el estudiante.',
                'esObligatoria' => true,
                'orden' => 9,
                'opciones' => ['Madre', 'Padre', 'Abuelo/a', 'Tío/a', 'Hermano/a mayor de edad', 'Tutor Legal']
            ],
            [
                'titulo' => 'Identificación del Tutor',
                'tipo' => 'texto_corto',
                'descripcion' => 'Número de documento del acudiente.',
                'esObligatoria' => true,
                'orden' => 10,
            ],
            [
                'titulo' => 'Teléfono del Tutor',
                'tipo' => 'texto_corto',
                'descripcion' => 'Teléfono de contacto prioritario para emergencias.',
                'esObligatoria' => true,
                'orden' => 11,
            ],
            [
                'titulo' => 'Correo del Tutor',
                'tipo' => 'texto_corto',
                'descripcion' => 'Dirección de correo electrónico del acudiente.',
                'esObligatoria' => true,
                'orden' => 12,
            ],
            [
                'titulo' => 'Certificado de Estudios de la Institución Anterior',
                'tipo' => 'texto_largo',
                'descripcion' => 'Por favor, suba el último certificado académico en formato PDF o Imagen.',
                'esObligatoria' => true,
                'orden' => 13,
            ],
        ];

        // 3. Insertar preguntas y opciones
        foreach ($preguntas as $pData) {
            $opciones = $pData['opciones'] ?? [];
            unset($pData['opciones']);

            $pregunta = FormularioPregunta::updateOrCreate(
                [
                    'idFormulario' => $formulario->id,
                    'titulo' => $pData['titulo']
                ],
                $pData
            );

            // Eliminar opciones existentes si se están recreando
            $pregunta->opciones()->delete();

            foreach ($opciones as $oIdx => $oTexto) {
                $pregunta->opciones()->create([
                    'texto' => $oTexto,
                    'orden' => $oIdx + 1
                ]);
            }
        }
    }
}
