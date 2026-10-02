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
        Schema::create('participante_evento', function (Blueprint $table) {
            $table->unsignedBigInteger('idPersona');
            $table->unsignedBigInteger('idEvento');
            $table->timestamp('fechaRegistro')->nullable();
            $table->string('estado')->default('CONFIRMADO');
            $table->timestamps();

            $table->primary(['idPersona', 'idEvento']);
            
            // Llaves foráneas (desactivadas temporalmente por inconsistencia de tipos)
            // $table->foreign('idPersona')->references('id')->on('persona')->onDelete('cascade');
            // $table->foreign('idEvento')->references('idEvento')->on('evento')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('participante_evento');
    }
};
