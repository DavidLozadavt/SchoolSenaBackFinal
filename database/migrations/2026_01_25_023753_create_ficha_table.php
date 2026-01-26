<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ficha', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idJornada');
            $table->unsignedBigInteger('idAsignacion')
                  ->comment('Relacion con la tabla asignación periodo programa');

            $table->timestamps();

            $table->string('codigo', 100)->nullable();
            $table->unsignedInteger('idInstructorLider')->nullable();
            $table->string('documento', 500)->nullable();
            
            // Primero se crea esta la tabla ficha descpues se les agrega los aprendices
            //Porque los aprendices y el vocero pertenecen a la tabla matricula:
            
            //$table->unsignedBigInteger('idAprendizVocero')->nullable();
            //$table->unsignedBigInteger('idAprendizSuplente')->nullable();
            $table->unsignedBigInteger('idInfraestructura')->nullable();
            $table->unsignedBigInteger('idSede')->nullable();

            $table->double('porcentajeEjecucion', 8, 2)->default(100.00);

            /* Índices */
            $table->index('idJornada');
            $table->index('idAsignacion');
            //$table->index('idAprendizVocero');
            //$table->index('idAprendizSuplente');
            $table->index('idInfraestructura');
            $table->index('idSede');

            /* Claves foráneas */
            $table->foreign('idJornada')
                  ->references('id')->on('jornadas');

            $table->foreign('idAsignacion')
                  ->references('id')->on('aperturarprograma');
            

            //$table->foreign('idAprendizVocero')
            //      ->references('id')->on('matricula');

            //$table->foreign('idAprendizSuplente')
            //      ->references('id')->on('matricula');

            $table->foreign('idInfraestructura')
                  ->references('id')->on('infraestructura');

            $table->foreign('idSede')
                  ->references('id')->on('sedes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha');
    }
};
