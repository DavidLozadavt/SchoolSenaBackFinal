<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormulariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('formularios', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idCompany');
            $table->unsignedInteger('idUser');
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            $table->string('slug', 100)->unique();
            $table->string('colorTema', 7)->default('#6366f1');
            $table->string('imagenCabecera')->nullable();
            $table->enum('estado', ['borrador', 'publicado', 'cerrado'])->default('borrador');
            $table->boolean('requiereAutenticacion')->default(false);
            $table->dateTime('fechaLimite')->nullable();
            $table->timestamps();

            $table->foreign('idCompany')->references('id')->on('empresa')->onDelete('cascade');
            $table->foreign('idUser')->references('id')->on('usuario')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('formularios');
    }
}
