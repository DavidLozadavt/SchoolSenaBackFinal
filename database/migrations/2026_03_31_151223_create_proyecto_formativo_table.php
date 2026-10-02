<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proyectoFormativo', function (Blueprint $table) {
            $table->id();
            $table->string('nombreProyecto');
            $table->string('version');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');

            // Foreign key
            $table->unsignedBigInteger('idPrograma');
            $table->foreign('idPrograma')->references('id')->on('programa');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectoFormativo');
    }
};
