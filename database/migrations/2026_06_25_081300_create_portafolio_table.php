<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('portafolio', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion');
            $table->unsignedInteger('idContrato');
            $table->timestamps();

            $table->foreign('idContrato')
                ->references('id')
                ->on('contrato');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portafolio');
    }
};