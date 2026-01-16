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
        Schema::create('detalleRevision', function (Blueprint $table) {
            $table->id();
            $table->text('nombre')->nullable();
            $table->unsignedBigInteger('idCompany');
            $table->timestamps();

            $table->foreign('idCompany')
                  ->references('id')
                  ->on('empresa')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('detalleRevision');
    }
};
