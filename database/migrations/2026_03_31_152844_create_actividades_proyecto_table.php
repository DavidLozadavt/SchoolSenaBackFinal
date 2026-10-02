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
        Schema::create('actividadesProyecto', function (Blueprint $table) {
            $table->id();
            
            $table->text('descripcionActividad');

            $table->unsignedBigInteger('idFaseProyecto');
            $table->foreign('idFaseProyecto')
                ->references('id')
                ->on('faseProyecto');

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
        Schema::dropIfExists('actividadesProyecto');
    }
};
