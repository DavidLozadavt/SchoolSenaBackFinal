<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseno de la escarapela de cada formulario (plantilla, colores, textos, logo
 * y fondo), en JSON. Las dos imagenes van dentro como data URL ya reducidas
 * por el frontend: asi la escarapela se puede exportar a PDF o PNG en el
 * navegador sin depender del CORS del almacenamiento.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('formularios', 'escarapelaDiseno')) {
            Schema::table('formularios', function (Blueprint $table) {
                $table->longText('escarapelaDiseno')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('formularios', 'escarapelaDiseno')) {
            Schema::table('formularios', function (Blueprint $table) {
                $table->dropColumn('escarapelaDiseno');
            });
        }
    }
};
