<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('anexosActa', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idacta');
            $table->string('nombre')->nullable();
            $table->string('archivo');
            $table->text('descripcion')->nullable();

            $table->timestamps();

            $table->foreign('idacta')->references('id')->on('acta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('anexosActa');
    }
};
