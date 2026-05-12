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
        Schema::create('asignacionCategoriaFormacionContrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idContrato')->notNull();
            $table->unsignedBigInteger('idCategoriaFormacion')->notNull();

            // relaciones
            $table->foreign('idContrato')
                ->references('id')
                ->on('contrato')
                ->onDelete('cascade');
                
            $table->foreign('idCategoriaFormacion', 'fk_asig_categoria')
                ->references('id')
                ->on('categoriaFormacion')
                ->onDelete('cascade');
            
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
        Schema::dropIfExists('asignacionCategoriaFormacionContratos');
    }
};
