<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('idSesionMateria');
            $table->unsignedBigInteger('idMatriculaAcademica');
            $table->timestamp('horaLLegada')->nullable();
            $table->boolean('asistio')->default(false);
            
            $table->timestamps();
            
            // Índices
            $table->index('idSesionMateria');
            $table->index('idMatriculaAcademica');
            
            // Claves foráneas
            $table->foreign('idSesionMateria')
                  ->references('id')
                  ->on('sesionMateria')
                  ->onDelete('cascade');
                  
            $table->foreign('idMatriculaAcademica')
                  ->references('id')
                  ->on('matriculaAcademica')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia');
    }
};