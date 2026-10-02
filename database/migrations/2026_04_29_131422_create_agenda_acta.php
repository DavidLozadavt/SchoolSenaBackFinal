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
        Schema::create('agendaActa', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idacta');
            $table->text('punto');

            $table->foreign('idacta')->references('id')->on('acta');

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
        Schema::dropIfExists('agendaActa');
    }
};
