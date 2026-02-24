<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignacionMaterialApoyoActividad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idActividad');
            $table->unsignedBigInteger('idMaterialApoyo');
            $table->timestamps();

            $table->foreign('idActividad', 'asignacionMaterialApoyoActividad_idactividad_foreign')
                ->references('id')->on('actividades');
            $table->foreign('idMaterialApoyo', 'asignacionMaterialApoyoActividad_idmaterialapoyo_foreign')
                ->references('id')->on('materialApoyoActividad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacionMaterialApoyoActividad');
    }
};
