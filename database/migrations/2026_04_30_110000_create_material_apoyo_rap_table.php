<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('materialApoyoRap')) {
            return;
        }

        Schema::create('materialApoyoRap', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFicha');
            $table->unsignedBigInteger('idMateria'); // competencia (contexto de programa)
            $table->unsignedBigInteger('idRap'); // RAP = fila en materia (hijo de competencia)
            $table->unsignedBigInteger('idPersona')->nullable(); // instructor / persona creadora
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            $table->string('urlDocumento', 500)->nullable();
            $table->string('urlAdicional', 500)->nullable();
            $table->string('urlVideo', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['idFicha', 'idRap'], 'materialapoyorap_ficha_rap_idx');
            $table->index('activo', 'materialapoyorap_activo_idx');

            $table->foreign('idFicha')
                ->references('id')
                ->on('ficha')
                ->cascadeOnDelete();

            $table->foreign('idMateria')
                ->references('id')
                ->on('materia')
                ->restrictOnDelete();

            $table->foreign('idRap')
                ->references('id')
                ->on('materia')
                ->restrictOnDelete();

            $table->foreign('idPersona')
                ->references('id')
                ->on('persona')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materialApoyoRap');
    }
};
