<?php

use App\Enums\TypePaymentMethodContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contrato', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('idpersona');
            $table->foreign('idpersona')->references('id')->on('persona');

            $table->unsignedInteger('idempresa');
            $table->foreign('idempresa')->references('id')->on('empresa');

            $table->unsignedInteger('idtipoContrato');
            $table->foreign('idtipoContrato')->references('id')->on('tipoContrato');

            $table->date('fechaContratacion');
            $table->date('fechaFinalContrato');

            $table->double('valorTotalContrato');
            $table->double('sueldo');
            $table->string('numeroContrato', 50);
            $table->text('objetoContrato');
            $table->text('observacion');
            $table->text('perfilProfesional');
            $table->char('otrosi', 1);

            $table->enum('formaPago', TypePaymentMethodContract::getValues())->nullable();
            $table->foreignId('idCentroCosto')->references('id')->on('centroCostos');

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
        Schema::dropIfExists('contracts');
    }
}
