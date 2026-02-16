<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();

            $table->string('nombre1');
            $table->string('nombre2');
            $table->string('apellido1');
            $table->string('apellido2');

            $table->string('tipo_identificacion');
            $table->string('identificacion');
            $table->string('correo');

            $table->string('celular')->nullable();
            $table->date('fecha_nacimiento')->nullable();

            $table->string('tipo_contratacion');
            $table->decimal('valor', 10, 2)->nullable();

            $table->date('fecha_inicial');
            $table->date('fecha_final');

            $table->string('rol');
            $table->string('area_conocimientos');

            $table->string('nivel_educativo', 200)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};