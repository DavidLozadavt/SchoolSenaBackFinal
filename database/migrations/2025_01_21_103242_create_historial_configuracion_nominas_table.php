<?php

use App\Enums\PayrollConfigurationStatus;
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
        Schema::create('historialConfiguracionNominas', function (Blueprint $table) {
            $table->id();

            $table->double('numHorasMes', 8, 2)->default(0);
            $table->double('diaPago', 8, 2)->default(0);
            $table->double('valorHorasExD', 8, 2)->default(0);
            $table->double('valorHorasExN', 8, 2)->default(0);
            $table->double('valorHorasFD', 8, 2)->default(0);
            $table->double('valorHorasFN', 8, 2)->default(0);
            $table->double('valorHorasExFD', 8, 2)->default(0);
            $table->double('valorHorasExFN', 8, 2)->default(0);
            $table->double('smlv', 18, 2)->default(0);
            $table->double('valAuxTransporte', 18, 2)->default(0);
            $table->decimal('porcentajeSalud', 5, 2)->default(0);
            $table->decimal('porcentajePension', 5, 2)->default(0);
            $table->decimal('porcentajeFsp', 5, 2)->default(0);

            $table->decimal('porcentajeAporteSalud', 5, 2)->default(0);
            $table->decimal('porcentajeAportePension', 5, 2)->default(0);
            $table->decimal('porcentajeAporteFsp', 5, 2)->default(0);

            $table->decimal('porcentajeCajaComp', 5, 2)->default(0);

            $table->decimal('porcentajeIcbf', 5, 2)->default(0);
            $table->decimal('porcentajeSena', 5, 2)->default(0);

            $table->decimal('porcentajeCesantias', 5, 2)->default(0);
            $table->decimal('porcentajeIntCesantias', 5, 2)->default(0);
            $table->decimal('porcentajePrima', 5, 2)->default(0);
            $table->decimal('porcentajeVacaciones', 5, 2)->default(0);

            $table->enum('comparacionAuxTrans', PayrollConfigurationStatus::getValues())->nullable();
            $table->double('numAuxTrans', 10, 2)->default(0);
            $table->enum('comparacionFsp', PayrollConfigurationStatus::getValues())->nullable();
            $table->double('numAuxFsp', 10, 2)->default(0);
            $table->enum('comparacionRetefuente', PayrollConfigurationStatus::getValues())->nullable();
            $table->double('valRetefuente', 10, 2)->default(0);
            $table->double('totalPagar', 10, 2)->default(0);
            $table->double('valUVT', 10, 2)->default(0);
            $table->double('numAuxUVTRetefuente', 10, 2)->default(0);
            $table->integer('periodo')->default(0);

            $table->double('incrementoAnualSMLV')->nullable()->comment('Porcentaje de incremento del salario minimo');

            $table->unsignedInteger('idEmpresa');
            $table->foreign('idEmpresa')->references('id')->on('empresa');

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
        Schema::dropIfExists('historial_configuracion_nominas');
    }
};
