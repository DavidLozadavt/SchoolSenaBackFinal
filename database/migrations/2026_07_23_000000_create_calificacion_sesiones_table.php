<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calificacionSesiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idSesionMateria');
            $table->unsignedBigInteger('idMatriculaAcademica');
            $table->tinyInteger('estrellas'); // Rango de 1 a 5 estrellas
            $table->text('comentarios')->nullable();
            $table->timestamps();

            // Relaciones
            $table->foreign('idSesionMateria', 'calif_sesion_materia_foreign')
                  ->references('id')->on('sesionMateria')
                  ->onDelete('cascade');
                  
            $table->foreign('idMatriculaAcademica', 'calif_matricula_foreign')
                  ->references('id')->on('matriculaAcademica')
                  ->onDelete('cascade');

            // Unicidad: un estudiante solo califica una vez cada sesión de clase
            $table->unique(['idSesionMateria', 'idMatriculaAcademica'], 'unique_alumno_sesion_calificacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calificacionSesiones');
    }
};
