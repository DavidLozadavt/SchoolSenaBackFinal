<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materialapoyoactividad', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion')->nullable();
            $table->string('titulo', 255)->nullable();
            $table->string('urlDocumento', 500)->nullable();
            $table->text('urlAdicional')->nullable();
            $table->unsignedBigInteger('idMateria');
            $table->timestamps();

            $table->foreign('idMateria', 'materialapoyoactividad_idmateria_foreign')
                ->references('id')->on('materia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materialapoyoactividad');
    }
};
