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
    public function up(): void
    {
        Schema::dropIfExists('novedadesActa');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::create('novedadesActa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idacta');
            $table->unsignedBigInteger('idmatriculaAcademica');
            $table->text('observacion')->nullable();

            $table->foreign('idacta')->references('id')->on('acta');
            $table->foreign('idmatriculaAcademica')->references('id')->on('matriculaAcademica');

            $table->timestamps();
        });
    }
};
