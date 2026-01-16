<?php

use App\Enums\SolicitudIncLicPersonas;
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
        Schema::create('solicitudIncLicPersonas', function (Blueprint $table) {
            $table->id();
            $table->date('fechaSolicitud');
            $table->date('fechaInicial');
            $table->date('fechaFinal');
            $table->string('urlSoporte')->nullable();

            $table->unsignedInteger('idContrato')->comment('Relacion con la persona (trabajador) quien hace la solicitud');
            $table->foreign('idContrato')->references('id')->on('contrato');

            $table->unsignedInteger('idContratoSupervisor')->nullable()->comment('Relacion con la persona quien crea la solicitud en este caso el supervisor');
            $table->foreign('idContratoSupervisor')->references('id')->on('contrato');

            $table->foreignId('idTipoIncapacidad')->references('id')->on('tipoIncapacidades');
            $table->integer('numDias');
            $table->double('valor');
            $table->enum('estado', SolicitudIncLicPersonas::getValues())->default(SolicitudIncLicPersonas::PENDIENTE);
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
        Schema::dropIfExists('solicitudIncLicPersonas');
    }
};
