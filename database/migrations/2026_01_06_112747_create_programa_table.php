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
        Schema::create('programa', function (Blueprint $table) {
            // El SQL usa bigint(20), en Laravel es id() o bigIncrements()
            $table->bigIncrements('id'); 
            
            $table->string('nombrePrograma', 255);
            $table->string('codigoPrograma', 255);
            $table->text('descripcionPrograma')->nullable();
            
            // Estas columnas DEBEN ser unsignedInteger para ser compatibles 
            // con el increments('id') de tus tablas de catálogo
            $table->unsignedInteger('idNivelEducativo');
            $table->unsignedInteger('idTipoFormacion');
            $table->unsignedInteger('idEstadoPrograma');
            $table->unsignedInteger('idCompany');

            $table->timestamps();

            // Definición de Llaves Foráneas (Relaciones)
            $table->foreign('idNivelEducativo', 'programa_idniveleducativo_foreign')
                  ->references('id')->on('nivelEducativo');
                  
            $table->foreign('idTipoFormacion', 'programa_idtipoformacion_foreign')
                  ->references('id')->on('tipoFormacion');
                  
            $table->foreign('idEstadoPrograma', 'programa_idestadoprograma_foreign')
                  ->references('id')->on('estadoPrograma');
            
         
            // $table->foreign('idCompany', 'programa_idcompany_foreign')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('programa');
    }
};
