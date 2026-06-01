<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEjecucionItemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ejecucionItem', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idHermano');
            $table->unsignedBigInteger('idItem');
            $table->boolean('recibido')->default(false);
            $table->dateTime('fecha_scan')->nullable();

            $table->foreign('idHermano')->references('id')->on('hermano')->onDelete('cascade');
            $table->foreign('idItem')->references('id')->on('item')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ejecucionItem');
    }
}
