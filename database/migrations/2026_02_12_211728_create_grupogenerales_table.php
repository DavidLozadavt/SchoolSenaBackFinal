<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupoGenerales', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->string('nombreGrupo');
            $table->string('imagen')->nullable();

            // Debe coincidir con usuario.id (int unsigned)
            $table->unsignedInteger('idUser');

            // Debe coincidir con empresa.id (int unsigned)
            $table->unsignedInteger('idCompany');

            $table->timestamps();

            $table->foreign('idUser')
                  ->references('id')
                  ->on('usuario')
                  ->cascadeOnDelete();

            $table->foreign('idCompany')
                  ->references('id')
                  ->on('empresa')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupoGenerales');
    }
};
