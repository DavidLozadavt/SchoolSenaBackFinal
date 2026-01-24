<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infraestructura', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('nombreInfraestructura');
            $table->unsignedInteger('capacidad');

            $table->unsignedBigInteger('idSede');

            $table->timestamps();

            // FK estilo jornadas
            $table->foreign('idSede', 'infraestructura_idsede_foreign')
                  ->references('id')
                  ->on('sedes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infraestructura');
    }
};
