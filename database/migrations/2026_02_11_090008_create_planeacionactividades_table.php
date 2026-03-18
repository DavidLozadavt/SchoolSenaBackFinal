<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planeacionActividades')) {
            return;
        }
        Schema::create('planeacionActividades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idActividad');
            $table->unsignedBigInteger('idMateria');
            $table->unsignedBigInteger('idPlaneacion');
            $table->timestamps();

            $table->foreign('idActividad', 'planeacionActividades_idactividad_foreign')
                ->references('id')->on('actividades');
            $table->foreign('idMateria', 'planeacionActividades_idmateria_foreign')
                ->references('id')->on('materia');
            $table->foreign('idPlaneacion', 'planeacionActividades_idplaneacion_foreign')
                ->references('id')->on('planeacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planeacionActividades');
    }
};
