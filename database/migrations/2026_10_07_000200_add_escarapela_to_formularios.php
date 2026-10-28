<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escarapela por formulario, se habilita desde la pestana "Escarapelas":
 * - escarapelaAlRegistrar: el inscrito recibe la suya al enviar el formulario.
 *   Null = automatico, como antes: solo si el formulario es de un evento.
 * - escarapelaIndividual / escarapelaMasiva: el administrador puede generarlas
 *   desde el panel, una por una o todas en un solo PDF.
 */
return new class extends Migration
{
    private const COLUMNAS = ['escarapelaAlRegistrar', 'escarapelaIndividual', 'escarapelaMasiva'];

    public function up()
    {
        if (!Schema::hasColumn('formularios', 'escarapelaAlRegistrar')) {
            Schema::table('formularios', function (Blueprint $table) {
                $table->boolean('escarapelaAlRegistrar')->nullable();
            });
        }

        foreach (['escarapelaIndividual', 'escarapelaMasiva'] as $columna) {
            if (!Schema::hasColumn('formularios', $columna)) {
                Schema::table('formularios', function (Blueprint $table) use ($columna) {
                    $table->boolean($columna)->default(false);
                });
            }
        }
    }

    public function down()
    {
        foreach (self::COLUMNAS as $columna) {
            if (Schema::hasColumn('formularios', $columna)) {
                Schema::table('formularios', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
