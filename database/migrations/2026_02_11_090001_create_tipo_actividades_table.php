<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_actividades', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipoActividad', 255);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedInteger('idCompany');
            $table->timestamps();

            $table->foreign('idCompany', 'tipo_actividades_idcompany_foreign')
                ->references('id')->on('empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_actividades');
    }
};
