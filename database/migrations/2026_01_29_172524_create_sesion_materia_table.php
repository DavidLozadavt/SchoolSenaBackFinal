<?php

use App\Enums\EstadoSesionMateria;
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
        Schema::create('sesionMateria', function (Blueprint $table) {
            $table->id();
            $table->integer('numeroSesion');
            $table->foreignId('idHorarioMateria')->references('id')->on('horarioMateria');
            $table->date('fechaSesion')->nullable();
            $table->enum('estado', EstadoSesionMateria::getValues())->default(EstadoSesionMateria::APLICA);
            $table->text('observacion')->nullable();
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
        Schema::dropIfExists('sesionMateria');
    }
};
