<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seguimientoAprendiz', function (Blueprint $table) {
            $table->id(); // Clave primaria autoincremental (id)
            $table->unsignedInteger('idpersona'); // Clave foránea a persona (estudiante)
            $table->unsignedInteger('idcontrato'); // Clave foránea a contrato (profesor) este es el que le hace el seguimiento al aprendiz

            // Definir claves foráneas
            $table->foreign('idpersona')->references('id')->on('persona');
            $table->foreign('idcontrato')->references('id')->on('contrato');


            $table->enum('estado', [
                'PENDIENTE',    // el seguimiento fue creado pero aún no ha iniciado actividad (sin documentos, sin visitas)
                'EN_PROCESO',   // en curso normal: hay documentos subidos, evaluaciones periódicas, todo avanza
                'ATRASADO',     // el estudiante está incumpliendo tiempos (no sube documentos, documentos rechazados sin corregir)
                'SUSPENDIDO',   // pausado por causa externa (incapacidad médica, empresa cierra temporalmente, etc.)
                'FINALIZADO',   // el contrato/etapa productiva terminó y el seguimiento se completó con éxito
                'CANCELADO',    // el contrato o el seguimiento se cancela antes de completarse
            ])->default('PENDIENTE');

            $table->timestamps(); // Opcional: created_at y updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('seguimiento');
    }
};
