<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sancion', function (Blueprint $table) {
            $table->id();

            $table->string('observacion')->nullable();
            $table->string('urlDocumento')->nullable();

            $table->date('fechaInicial');
            $table->date('fechaFinal');

            $table->unsignedBigInteger('idAnotacionesDisciplinarias');
            $table->unsignedInteger('idEstado')->nullable();
            $table->unsignedInteger('idDocente');

            $table->enum('gradoSancion', [
                'Amonestación verbal',
                'Amonestación escrita',
                'Suspensión temporal',
                'Cancelación de matrícula'
            ])->default('Amonestación verbal');

            $table->timestamps();

            // Foreign Keys
            $table->foreign('idAnotacionesDisciplinarias')
                  ->references('id')
                  ->on('anotacionesDisciplinarias')
                  ->onDelete('cascade');

            $table->foreign('idDocente')
                  ->references('id')
                  ->on('contrato')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sancion');
    }
};