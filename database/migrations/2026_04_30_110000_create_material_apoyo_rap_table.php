<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'materialApoyoRap';
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFicha');
            $table->unsignedBigInteger('idMateria');
            $table->unsignedBigInteger('idRap');
            $table->unsignedInteger('idPersona')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('titulo', 255)->nullable();
            $table->string('urlDocumento', 500)->nullable();
            $table->text('urlAdicional')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('idFicha', 'materialapoyorap_idficha_foreign')
                ->references('id')->on('ficha');
            $table->foreign('idMateria', 'materialapoyorap_idmateria_foreign')
                ->references('id')->on('materia');
            $table->foreign('idRap', 'materialapoyorap_idrap_foreign')
                ->references('id')->on('materia');
            $table->foreign('idPersona', 'materialapoyorap_idpersona_foreign')
                ->references('id')->on('persona')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materialApoyoRap');
    }
};
