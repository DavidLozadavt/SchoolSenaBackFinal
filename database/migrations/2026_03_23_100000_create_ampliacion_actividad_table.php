<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controla ampliaciones de tiempo en actividades.
     */
    public function up(): void
    {
        if (Schema::hasTable('ampliacionActividad')) {
            return;
        }

        Schema::create('ampliacionActividad', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('idCalificacionActividad');
            $table->string('observacion', 250)->nullable();
            $table->dateTime('fechaExtendida')->nullable();
            $table->timestamps();

            $table->foreign('idCalificacionActividad', 'ampliacionactividad_idcalificacionactividad_foreign')
                ->references('id')->on('calificacionActividad')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ampliacionActividad');
    }
};
