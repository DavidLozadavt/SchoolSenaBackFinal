<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gradoprograma', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idPrograma');
            $table->unsignedBigInteger('idGrado');

            $table->integer('cupos')
                ->nullable()
                ->comment('Cantidad de estudiantes asignados al programa');

            $table->timestamps();

            $table->unique(['idPrograma', 'idGrado'], 'gradoprograma_idprograma_idgrado_unique');

            $table->foreign('idPrograma')
                ->references('id')
                ->on('programa');
            $table->foreign('idGrado')
                ->references('id')
                ->on('grado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gradoprograma');
    }
};
