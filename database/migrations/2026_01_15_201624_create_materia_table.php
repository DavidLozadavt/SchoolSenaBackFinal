<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materia', function (Blueprint $table) {
            $table->id(); 
            $table->string('nombreMateria', 255);
            $table->text('descripcion')->nullable();
            
            $table->string('rutaDoc', 500)->nullable(); 
            
            $table->unsignedInteger('idEmpresa'); 
            $table->unsignedInteger('idAreaConocimiento'); 
            
            $table->unsignedBigInteger('idMateriaPadre')->nullable();
            $table->string('codigo', 20)->nullable();
            $table->integer('creditos')->nullable();
            $table->decimal('horas', 5, 2)->nullable();
            
            $table->timestamps();

            // --- ÍNDICES Y LLAVES ---
            $table->index('idEmpresa');
            $table->index('idAreaConocimiento');

            $table->foreign('idMateriaPadre')->references('id')->on('materia')->onDelete('set null');
            
            $table->unique(['nombreMateria', 'idAreaConocimiento', 'idEmpresa'], 'materia_unique_composite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materia');
    }
};