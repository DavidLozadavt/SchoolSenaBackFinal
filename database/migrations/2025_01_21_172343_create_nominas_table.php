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
        Schema::create('nominas', function (Blueprint $table) {
            $table->id();

            $table->integer('valHorasBase');
            $table->double('pagoNominaParcial', 18, 2);
            // $table->double('valHorasExD', 18, 2);
            // $table->double('valHorasExN', 18, 2);
            // $table->double('valHorasFD', 18, 2);
            // $table->double('valHorasFN', 18, 2);
            // $table->double('valHorasExFD', 18, 2);
            // $table->double('valHorasExFN', 18, 2);
            $table->double('comisiones', 18, 2)->default(0);
            $table->double('valorHorasExtras')->default(0);
            $table->double('subTotal', 18, 2)->default(0);
            $table->double('auxTransporte', 18, 2);
            $table->double('devengado', 18, 2);
            $table->double('salud', 18, 2)->default(0);
            $table->double('pension', 18, 2)->default(0);
            $table->double('fsp', 18, 2)->default(0);
            $table->double('netoPagado', 18, 2)->default(0);
            $table->double('aportesSalud', 18, 2)->default(0);
            $table->double('aportesPension', 18, 2)->default(0);
            $table->double('aportesFsp', 18, 2)->default(0);
            $table->double('totalDectoSegSocial', 18, 2)->default(0)->comment('Total descuento seguridad social');
            $table->double('intCes', 18, 2)->default(0)->comment('Intereses sobre cesantias');
            $table->double('prima', 18, 2)->default(0);
            $table->double('vacaciones', 18, 2)->default(0);
            
            $table->double('cajaCompensacion', 18, 2)->default(0);
            $table->double('icbf', 18, 2)->default(0);
            $table->double('sena', 18, 2)->default(0);
            $table->double('aportFiscales', 18, 2)->default(0)->comment('Aportes para fiscales');
            $table->double('arl', 18, 2)->default(0);
            $table->double('totalApropiaciones', 18, 2)->default(0);
            $table->double('valorTotalPorTrabajador', 18, 2)->default(0);
            
            $table->double('retefuente', 18, 2)->default(0);

            // $table->foreignId('idCentroCosto')->references('id')->on('centroCostos');
            
            $table->unsignedInteger('idContrato');
            $table->foreign('idContrato')->references('id')->on('contrato');

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
        Schema::dropIfExists('nominas');
    }
};
