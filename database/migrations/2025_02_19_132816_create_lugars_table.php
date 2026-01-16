<?php

use App\Enums\PlaceType;
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
        Schema::create('lugares', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');

            $table->unsignedInteger('idCiudad');
            $table->foreign('idCiudad')->references('id')->on('ciudad');

            $table->enum('tipoLugar', PlaceType::getValues());

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
        Schema::dropIfExists('lugars');
    }
};
