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

            $table->unsignedBigInteger('idTipoGrado');

            $table->timestamps();

            /* Índice */
            $table->index('idTipoGrado');

            /* Clave foránea */
            $table->foreign('idTipoGrado')
                  ->references('id')
                  ->on('tipogrado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grado');
    }
};
