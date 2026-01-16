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
        Schema::create('tipoIncapacidades', function (Blueprint $table) {
            $table->id();

            $table->string('tipoIncapacidad');
            $table->string('responsable');
            $table->double('porcentajeDePago', 8, 2);
            $table->longText('duracionCubierta');
            $table->text('descripcion')->nullable();

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
        Schema::dropIfExists('tipo_incapacidads');
    }
};
