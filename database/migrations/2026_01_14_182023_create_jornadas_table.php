<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('nombreJornada');
            $table->text('descripcion')->nullable();

            $table->enum('diaSemana', [
                'Lunes',
                'Martes',
                'Miércoles',
                'Jueves',
                'Viernes',
                'Sábado',
                'Domingo'
            ]);

            $table->time('horaInicial');
            $table->time('horaFinal');

            $table->float('numeroHoras')->nullable();

            $table->enum('estado', ['Activo', 'Inactivo'])->default('Activo');

            $table->integer('grupoJornada')
                  ->nullable()
                  ->comment('Bandera para agrupar jornadas');

            // 🔑 CLAVE: igual que periodo
            $table->unsignedInteger('idEmpresa');

            $table->unsignedBigInteger('idTipoJornada');

            $table->timestamps();

            // FKs
            $table->foreign('idEmpresa', 'jornadas_idempresa_foreign')
                  ->references('id')
                  ->on('empresa')
                  ->onDelete('cascade');

            $table->foreign('idTipoJornada', 'jornadas_idtipojornada_foreign')
                  ->references('id')
                  ->on('tipojornada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornadas');
    }
};
