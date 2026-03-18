<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Debe ejecutarse ANTES de calificacionactividad (referencia idCorte).
     */
    public function up(): void
    {
        if (Schema::hasTable('configuracionCortes')) {
            return;
        }
        Schema::create('configuracionCortes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idPeriodo');
            $table->string('detalle', 255);
            $table->integer('porcentaje');
            $table->date('fechaInicial');
            $table->date('fechaFinal');
            $table->timestamps();

            $table->foreign('idPeriodo', 'configuracioncortes_idperiodo_foreign')
                ->references('id')->on('periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracionCortes');
    }
};
