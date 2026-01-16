<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grado', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedInteger('numeroGrado'); 
            $table->string('nombreGrado', 255); 
            
            $table->foreignId('idTipoGrado')->constrained('tipoGrado');
            
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grado');
    }
};