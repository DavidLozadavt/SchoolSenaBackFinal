<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->unsignedInteger('id');
            $table->string('urlArchivo', 255);
            $table->string('nombreArchivo', 255)->nullable();
            $table->tinyInteger('aprobada')->default(0);
            $table->unsignedInteger('carpetas_viajeras_id');

            $table->primary(['id', 'carpetas_viajeras_id']);

            $table->foreign('carpetas_viajeras_id')
                ->references('id')->on('carpetas_viajeras')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};