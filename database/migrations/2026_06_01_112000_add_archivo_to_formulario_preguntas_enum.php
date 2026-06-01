<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddArchivoToFormularioPreguntasEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // En MariaDB/MySQL alteramos directamente la columna ENUM agregando 'archivo' al final de la lista.
        DB::statement("ALTER TABLE formulario_preguntas MODIFY COLUMN tipo ENUM('texto_corto', 'texto_largo', 'opcion_multiple', 'casillas', 'desplegable', 'escala_lineal', 'fecha', 'hora', 'archivo') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE formulario_preguntas MODIFY COLUMN tipo ENUM('texto_corto', 'texto_largo', 'opcion_multiple', 'casillas', 'desplegable', 'escala_lineal', 'fecha', 'hora') NOT NULL");
    }
}
