<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asignacion_ficha_tipo_documento')) {
            return;
        }

        Schema::create('asignacion_ficha_tipo_documento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFicha');
            $table->unsignedInteger('idTipoDocumento');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            /* Índices */
            $table->unique(['idFicha', 'idTipoDocumento'], 'asig_ficha_tipodoc_unique');

            /* Claves foráneas */
            $table->foreign('idFicha', 'asignacion_ficha_tipo_documento_idficha_foreign')
                  ->references('id')->on('ficha')->onDelete('cascade');
            $table->foreign('idTipoDocumento', 'asignacion_ficha_tipo_documento_idtipodocumento_foreign')
                  ->references('id')->on('tipoDocumento')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_ficha_tipo_documento');
    }
};
