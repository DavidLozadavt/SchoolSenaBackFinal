<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('nucleoFamiliar', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('idEstudiante');
            $table->unsignedInteger('idAcudiente')->comment('Familiar del estudiante');
            $table->enum('parentesco', ['PADRE', 'MADRE', 'TIO(A)', 'ABUELO(A)', 'PRIMO(A)'])->nullable();
            $table->boolean('tutorLegal')->nullable();
            $table->boolean('vive')->default(1)->comment('Si la persona se encuentra con vida');
            $table->tinyInteger('padresSeparados')->default(3)->comment('Verificar si los padres se encuentran separados entre sí, 0 => separados, 1 => no separados, 3 => no aplica');
            $table->boolean('custodia')->nullable()->comment('Si están separados verificar quien tiene la custodia, sino ambos deberán tener la custodia como true');
            $table->string('documentoCustodia', 255)->nullable();
            $table->string('nombreEmpresa', 255)->nullable();
            $table->text('profesion')->nullable();
            $table->unsignedInteger('idCiudadTrabajo')->nullable()->comment('Ciudad donde trabaja la persona');
            $table->string('direccionEmpresa', 255)->nullable()->comment('dirección de la empresa donde trabaja la persona');
            $table->string('telefonoEmpresa', 255)->nullable()->comment('telefono de la empresa donde trabaja la persona');
            $table->string('celularEmpresa', 255)->nullable()->comment('Ciudad de la empresa donde trabaja la persona');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->tinyInteger('intentoAsignarTutor')->default(0);

            $table->foreign('idEstudiante')->references('id')->on('persona');
            $table->foreign('idAcudiente')->references('id')->on('persona');
            $table->foreign('idCiudadTrabajo')->references('id')->on('ciudad');
        });
    }

    public function down()
    {
        Schema::dropIfExists('nucleoFamiliar');
    }
};
