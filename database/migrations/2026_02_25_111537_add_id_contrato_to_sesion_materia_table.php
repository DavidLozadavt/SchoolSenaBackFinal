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
    /*
    SENTENCIAS SQL
        ALTER TABLE `sesionMateria` ADD `idContrato` BIGINT UNSIGNED NOT NULL;
        ALTER TABLE `sesionMateria` ADD CONSTRAINT `sesionmateria_idcontrato_foreign` FOREIGN KEY (`idContrato`) REFERENCES `contrato` (`id`);
    
        NOTA
        ¡ si tienes registros en la tabla sesionMateria, no puedes ejecutar esta migracion correctamente
        ya que la columna idContrato no puede ser nulo. !

        -puedes borrar los registros de la tabla sesionMateria y ejecutar la migracion.
        -o puedes ejecutar con un nullable(true) pero para corregir los registros de la tabla sesionMateria 
        y luego activar el nullable(false) 
    */
    public function up()
    {
        Schema::table('sesionMateria', function (Blueprint $table) {
            $table->unsignedInteger('idContrato')->nullable(true);
            $table->foreign('idContrato')->references('id')->on('contrato');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sesionMateria', function (Blueprint $table) {
            //
        });
    }
};
