<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notificacion', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fecha');
            $table->time('hora');
            $table->char('asunto', 50);
            $table->text('mensaje');
            $table->unsignedInteger('estado_id');
            $table->foreign('estado_id')->references('id')->on('estado');
            $table->foreign('idUsuarioReceptor')->references('id')->on('usuario');
            $table->unsignedInteger('idUsuarioReceptor');
            $table->foreign('idUsuarioRemitente')->references('id')->on('usuario');
            $table->unsignedInteger('idUsuarioRemitente');
            $table->foreign('idTipoNotificacion')->references('id')->on('tipoNotificacion');
            $table->unsignedInteger('idTipoNotificacion');
            $table->foreign('idEmpresa')->references('id')->on('empresa');
            $table->unsignedInteger('idEmpresa');
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
        Schema::dropIfExists('notificacion');
    }
}
