<?php

use App\Enums\EstadoSeguimientoMateria;
use App\Enums\TipoMateriaSeguimiento;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seguimientoMateria', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idMateria')->references('id')->on('materia');

            $table->foreignId('idFicha')->references('id')->on('ficha');

            $table->enum('tipoMateria', TipoMateriaSeguimiento::getValues());

            $table->enum('estado', EstadoSeguimientoMateria::getValues())->default(EstadoSeguimientoMateria::POR_EVALUAR);

            $table->double('horasTotales')->default(0);

            $table->foreignId('idMateriaPadre')->nullable()->references('id')->on('materia');

            $table->double('horasEjecutadas')->nullable();
            
            $table->double('horasFaltantes')->nullable();

            $table->double('porcentajeEjecutado')->default(0);

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
        //
    }
};
