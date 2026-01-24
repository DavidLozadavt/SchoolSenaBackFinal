<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Agregar idCiudad si no existe
            if (!Schema::hasColumn('sedes', 'idCiudad')) {
                $table->unsignedInteger('idCiudad')->nullable()->after('idEmpresa'); // <-- cambio a unsignedInteger
                $table->foreign('idCiudad')->references('id')->on('ciudad')->onUpdate('cascade')->onDelete('set null');
            }

            // Agregar columna tipo (varchar) si no existe
            if (!Schema::hasColumn('sedes', 'tipo')) {
                $table->string('tipo', 50)->nullable()->after('idCiudad'); 
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Eliminar idCiudad
            if (Schema::hasColumn('sedes', 'idCiudad')) {
                $table->dropForeign(['idCiudad']);
                $table->dropColumn('idCiudad');
            }

            // Eliminar columna tipo
            if (Schema::hasColumn('sedes', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
