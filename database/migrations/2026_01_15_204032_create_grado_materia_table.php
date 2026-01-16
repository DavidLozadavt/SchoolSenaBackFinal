<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradoMateria', function (Blueprint $table) {
            $table->id();
            $table->text('rutaDocumento')->nullable();
            
            // Relaciones principales
            $table->foreignId('idGradoPrograma')->constrained('gradoPrograma');
            $table->foreignId('idMateria')->constrained('materia');
            
         
            $table->unsignedBigInteger('idDocente')->nullable()->comment('ID del usuario activado en la compañía');
            
            $table->timestamps();

            $table->unique(['idMateria', 'idGradoPrograma'], 'gradomateria_idmateria_idgradoprograma_unique');
            
            $table->index('idDocente', 'gradomateria_iddocente_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gradoMateria');
    }
};