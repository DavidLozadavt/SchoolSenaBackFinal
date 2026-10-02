<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividadesInstructores', function (Blueprint $table) {
            $table->id();

            $table->text('descripcion');
            $table->date('fechaInicial');
            $table->date('fechaFinal');
            $table->unsignedInteger('numeroHoras');

            $table->unsignedBigInteger('idRmi');

            $table->timestamps();

            $table->foreign('idRmi')
                ->references('id')
                ->on('rmi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividadesInstructores');
    }
};
