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
        Schema::create('solicitudVacaciones', function (Blueprint $table) {
            $table->id();

            $table->date('fechaSolicitud');
            $table->timestamp('fechaLiquidacion')->nullable();
            $table->date('fechaEjecucion')->nullable();
            $table->json('periodos');
            $table->enum('estado', StatusVacaciones::getValues())->default(StatusVacaciones::PENDIENTE);
            $table->integer('numDias');
            $table->double('valor');
            $table->date('fechaFinal');

            $table->unsignedInteger('idContratoSupervisor')->nullable()->comment('Relacion con la persona quien crea la solicitud en este caso el supervisor');
            $table->foreign('idContratoSupervisor')->references('id')->on('contrato');

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
        Schema::dropIfExists('solicitud_vacacions');
    }
};
