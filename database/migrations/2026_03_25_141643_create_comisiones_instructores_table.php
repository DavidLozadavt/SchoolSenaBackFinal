<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisionesInstructores', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('numeroViaje');
            $table->string('lugarDesplazamiento');
            $table->date('fechaInicialDesplazamiento');
            $table->date('fechaFinalDesplazamiento');

            $table->unsignedInteger('idContrato');
            $table->unsignedBigInteger('idRmi');
            $table->string('item', 250)->nullable();

            $table->timestamps();

            $table->foreign('idContrato')
                ->references('id')
                ->on('contrato');

            $table->foreign('idRmi')
                ->references('id')
                ->on('rmi'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisionesInstructores');
    }
};
