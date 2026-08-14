<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carpetas_viajeras', function (Blueprint $table) {
            $table->increments('id'); // int unsigned auto_increment, igual que persona.id
            $table->decimal('total', 12, 2)->default(0.00);// total pago para el instructor
            $table->enum('nivel_academico', ['BACHILLERATO', 'TECNICO', 'TECNOLOGO']); //Donde el dio la clase
            $table->string('modulo', 255); //Modulo que dio el profesor
            $table->tinyInteger('aprobada')->default(0); // lo que hizo el profesor en la carpeta viajera
            $table->enum('pago', ['PENDIENTE', 'ENTREGADO', 'CANCELADO'])->default('PENDIENTE'); //estado del pago
            $table->integer('total_horas'); //total de horas de la carpeta viajera
            $table->unsignedInteger('persona_id'); //id del usuario que creo la carpeta viajera
            $table->string('codigo_transferencia', 45)->nullable(); //codigo de transferencia para el pago

            $table->foreign('persona_id')
                ->references('id')->on('persona')
                ->onDelete('no action')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carpetas_viajeras');
    }
};