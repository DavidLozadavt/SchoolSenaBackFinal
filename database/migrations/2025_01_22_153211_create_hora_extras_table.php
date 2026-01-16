<?php

use App\Enums\OverTimeType;
use App\Enums\StatusType;
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
        Schema::create('horaExtras', function (Blueprint $table) {
            $table->id();

            $table->enum('tipoHoraExtra', OverTimeType::getValues());
            
            $table->integer('numeroHoras');

            $table->double('valorHoraExtra', 18, 2);

            $table->date('fecha');

            $table->enum('estado', StatusType::getValues())->default(StatusType::PENDIENTE);

            $table->string('urlEvidencia')->nullable();

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
        Schema::dropIfExists('hora_extras');
    }
};
