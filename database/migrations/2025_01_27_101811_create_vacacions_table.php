<?php

use App\Enums\StatusVacaciones;
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
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->id();

            $table->integer('periodo');

            $table->enum('estado', StatusVacaciones::getValues());

            $table->foreignId('idSolicitud')->nullable()->references('id')->on('solicitudVacaciones');
            
            $table->unsignedInteger('idContrato');
            $table->foreign('idContrato')->references('id')->on('contrato');

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
        Schema::dropIfExists('vacaciones');
    }
};
