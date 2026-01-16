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
        Schema::create('asignacionComentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commentable_id')->nullable()->comment('Id del usuario');
            $table->foreignId('idGrupo')->nullable()->references('id')->on('gruposChat');
            $table->foreignId('idComentario')->references('id')->on('comentarios');
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
        Schema::dropIfExists('asignacion_comentarios');
    }
};
