<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gruposChat', function (Blueprint $table) {
            $table->id();

            $table->string('nombreGrupo');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('INACTIVO');
            $table->text('descripcion')->nullable();
            $table->integer('cantidadParticipantes');

            $table->foreignId('idTipoGrupo')->references('id')->on('tipoGrupo');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grupos_chat');
    }
};
