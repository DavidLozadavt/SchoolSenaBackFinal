<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participanteGrupoGenerales', function (Blueprint $table) {

            $table->bigIncrements('id');

            // FK hacia persona (int unsigned)
            $table->unsignedInteger('idPersona');

            // FK hacia grupogenerales (bigint unsigned)
            $table->unsignedBigInteger('idGrupo');

            $table->timestamps();

            // Foreign keys
            $table->foreign('idPersona')
                  ->references('id')
                  ->on('persona');

            $table->foreign('idGrupo')
                  ->references('id')
                  ->on('grupogenerales');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participanteGrupoGenerales');
    }
};
