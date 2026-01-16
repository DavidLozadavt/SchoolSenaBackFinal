<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePeriodosTable extends Migration
{
    public function up(): void
{
    Schema::create('periodo', function (Blueprint $table) {
        $table->bigIncrements('id'); 

        $table->string('nombrePeriodo');
        $table->date('fechaInicial');
        $table->date('fechaFinal');

        $table->unsignedInteger('idEmpresa');

        $table->timestamps();

        $table->foreign('idEmpresa', 'periodo_idempresa_foreign')
              ->references('id')
              ->on('empresa')
              ->onDelete('cascade');
    });
}

    public function down(): void
    {
        Schema::dropIfExists('periodo');
    }
}