<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excusa', function (Blueprint $table) {
            $table->id();

            $table->date('fechaInicialJustificacion')
                  ->nullable()
                  ->comment('Fecha inicial si es por días la excusa');

            $table->date('fechaFinalJustificacion')
                  ->nullable()
                  ->comment('Fecha final si es por días la excusa');

            $table->time('horaInicialJustificacion')
                  ->nullable()
                  ->comment('Hora inicial de la justificación si es por horas');

            $table->time('horaFinalJustificacion')
                  ->nullable()
                  ->comment('Hora final de la justificación si es por horas');

            $table->string('urlDocumento', 255)
                  ->nullable()
                  ->comment('El documento no es requerido como tal');

            $table->text('observacion')->nullable();

            $table->enum('tipoExcusa', [
                'PERMISO ESTUDIANTIL',
                'PERMISO LABORAL',
                'PERMISO MEDICO',
                'FUERZA MAYOR'
            ]);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excusa');
    }
};