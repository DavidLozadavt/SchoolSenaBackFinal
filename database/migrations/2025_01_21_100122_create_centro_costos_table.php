<?php

use App\Enums\StatusCostCenter;
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
        Schema::create('centroCostos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idSede');
            $table->foreign('idSede')->references('id')->on('sedes');

            $table->unsignedBigInteger('idArea');
            $table->foreign('idArea')->references('id')->on('areas');

            $table->text('descripcion');

            $table->decimal('presupuesto', 18, 2);

            $table->integer('año');
            $table->enum('estado', StatusCostCenter::getValues())->default(StatusCostCenter::ENCURSO);

            $table->date('fechaInicial')->nullable();
            $table->date('fechaFinal')->nullable();

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
        Schema::dropIfExists('centro_costos');
    }
};
