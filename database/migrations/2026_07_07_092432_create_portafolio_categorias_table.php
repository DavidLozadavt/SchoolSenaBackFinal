<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('portafolioCategorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('slug', 150);
            $table->unsignedBigInteger('idCategoriaPadre')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('idCategoriaPadre')
                ->references('id')
                ->on('portafolioCategorias');
        });

        Schema::table('portafolioDocumentos', function (Blueprint $table) {
            $table->unsignedBigInteger('idCategoria')
                ->nullable()
                ->after('idPortafolioFichas');

            $table->foreign('idCategoria')
                ->references('id')
                ->on('portafolioCategorias');
        });
    }

    public function down()
    {
        Schema::table('portafolioDocumentos', function (Blueprint $table) {
            $table->dropForeign(['idCategoria']);
            $table->dropColumn('idCategoria');
        });

        Schema::dropIfExists('portafolioCategorias');
    }
};