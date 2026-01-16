<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradoPrograma', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('idPrograma')->constrained('programa'); 
            $table->foreignId('idGrado')->constrained('grado');
            
            $table->integer('cupos')->nullable()->comment('Cantidad de estudiantes asignados al programa');
            
            $table->timestamps();

            $table->unique(['idPrograma', 'idGrado'], 'gradoprograma_idprograma_idgrado_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gradoPrograma');
    }
};