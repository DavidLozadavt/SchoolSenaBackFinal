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
public function up(): void
{
    // Verificar si la tabla ya existe antes de crearla
    if (Schema::hasTable('asignacionPeriodoPrograma')) {
        return; // La tabla ya existe, no hacer nada
    }
    
    Schema::create('asignacionPeriodoPrograma', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->text('observacion')->nullable();
        
        $table->unsignedBigInteger('idPeriodo');
        $table->unsignedBigInteger('idPrograma');

        // Enum de Estados
        $table->enum('estado', [
            'ACTIVO','INACTIVO','OCULTO','PENDIENTE','RECHAZADO','APROBADO',
            'CANCELADO','REPROBADO','CERRADO','ACEPTADO','LEIDO','EN ESPERA',
            'INSCRIPCION','MATRICULADO','ABIERTO','EN CURSO','POR ACTUALIZAR',
            'CURSANDO','ENTREVISTA','SIN ENTREVISTA','JUSTIFICADO'
        ])->default('INACTIVO');

        $table->unsignedBigInteger('idSede'); 
        $table->boolean('pension')->default(true);
        $table->unsignedBigInteger('diaCobro')->nullable();

        $table->date('fechaInicialClases')->nullable();
        $table->date('fechaFinalClases')->nullable();
        $table->date('fechaInicialInscripciones')->nullable();
        $table->date('fechaFinalInscripciones')->nullable();
        $table->date('fechaInicialMatriculas')->nullable();
        $table->date('fechaFinalMatriculas')->nullable();
        $table->date('fechaInicialPlanMejoramiento')->nullable();
        $table->date('fechaFinalPlanMejoramiento')->nullable();

        // Bloque de Valores y Porcentajes
        $table->double('porcentajeMoraMatricula', 8, 2)->nullable();
        $table->decimal('valorPension', 40, 2)->default(0.00);
        $table->unsignedBigInteger('diasMoraMatricula')->nullable();
        $table->double('porcentajeMoraPension', 8, 2)->nullable();
        $table->enum('tipoCalificacion', ['NUMERICO', 'DESEMPEÑO'])->default('NUMERICO');
        $table->unsignedBigInteger('diasMoraPension')->nullable();

        $table->timestamps(); 

        // Crear claves foráneas solo si las tablas existen
        if (Schema::hasTable('periodo')) {
            $table->foreign('idPeriodo')->references('id')->on('periodo');
        }
        if (Schema::hasTable('programa')) {
            $table->foreign('idPrograma')->references('id')->on('programa');
        }
        if (Schema::hasTable('sedes')) {
            $table->foreign('idSede')->references('id')->on('sedes');
        }
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
   public function down()
{
    Schema::dropIfExists('asignacionperiodoprograma');
}
};
