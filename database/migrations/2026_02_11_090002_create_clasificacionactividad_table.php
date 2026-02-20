<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clasificacionactividad', function (Blueprint $table) {
            $table->id();
            $table->string('nombreClasificacionActividad', 255);
            $table->integer('porcentaje');
            $table->unsignedInteger('idCompany')->nullable();
            $table->unsignedBigInteger('idPrograma')->nullable();
            $table->timestamps();

            $table->foreign('idCompany', 'clasificacionactividad_idcompany_foreign')
                ->references('id')->on('empresa')->onDelete('set null');
            $table->foreign('idPrograma', 'clasificacionactividad_idprograma_foreign')
                ->references('id')->on('programa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificacionactividad');
    }
};
