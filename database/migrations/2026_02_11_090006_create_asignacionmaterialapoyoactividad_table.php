<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignacionmaterialapoyoactividad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idActividad');
            $table->unsignedBigInteger('idMaterialApoyo');
            $table->timestamps();

            $table->foreign('idActividad', 'asignacionmaterialapoyoactividad_idactividad_foreign')
                ->references('id')->on('actividades');
            $table->foreign('idMaterialApoyo', 'asignacionmaterialapoyoactividad_idmaterialapoyo_foreign')
                ->references('id')->on('materialapoyoactividad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacionmaterialapoyoactividad');
    }
};
