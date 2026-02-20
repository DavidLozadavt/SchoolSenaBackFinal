<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preguntas')) {
            return;
        }
        Schema::create('preguntas', function (Blueprint $table) {
            $table->increments('id');
            $table->text('descripcion');
            $table->double('puntaje');
            $table->unsignedInteger('idTipoPregunta');
            $table->unsignedBigInteger('idActividad');
            $table->text('urlDocumento')->nullable();

            $table->foreign('idTipoPregunta', 'preguntas_idtipopregunta_foreign')
                ->references('id')->on('tipo_preguntas');
            $table->foreign('idActividad', 'preguntas_idactividad_foreign')
                ->references('id')->on('actividades');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preguntas');
    }
};
