<?php

use App\Enums\Dias;
use App\Enums\DiasEnum;
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
        Schema::create('agendarViajes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idViaje')->references('id')->on('viajes');

            $table->enum('dia', [DiasEnum::getValues()]);

            $table->date('fecha');

            $table->time('hora');

            $table->boolean('repetir')->default(false);

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
        Schema::dropIfExists('agendarViajes');
    }
};
