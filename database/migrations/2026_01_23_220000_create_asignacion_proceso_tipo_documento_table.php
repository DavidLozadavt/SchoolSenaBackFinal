<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asignacion_proceso_tipo_documento')) {
            return;
        }

        Schema::create('asignacion_proceso_tipo_documento', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idProceso');
            $table->unsignedInteger('idTipoDocumento');

            $table->foreign('idProceso')->references('id')->on('proceso')->onDelete('cascade');
            $table->foreign('idTipoDocumento')->references('id')->on('tipoDocumento')->onDelete('cascade');
            $table->unique(['idProceso', 'idTipoDocumento'], 'asig_proceso_tipodoc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_proceso_tipo_documento');
    }
};
