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
        Schema::create('rmi', function (Blueprint $table) {
            $table->id();
            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->string('periodo', 7); // formato Y-m ej: 2026-03
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
        Schema::dropIfExists('rmi');
    }
};
