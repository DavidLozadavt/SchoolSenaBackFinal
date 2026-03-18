<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Asegura que la tabla tipoPreguntas exista (camelCase) y tenga los datos.
     * Compatible con instalaciones que tengan tipo_preguntas (snake_case).
     */
    public function up(): void
    {
        $tableCamel = 'tipoPreguntas';
        $tableSnake = 'tipo_preguntas';

        // Si existe tipo_preguntas pero no tipoPreguntas, renombrar
        if (Schema::hasTable($tableSnake) && !Schema::hasTable($tableCamel)) {
            Schema::rename($tableSnake, $tableCamel);
        }

        // Si no existe ninguna, crear tipoPreguntas
        if (!Schema::hasTable($tableCamel)) {
            Schema::create($tableCamel, function (Blueprint $table) {
                $table->increments('id');
                $table->string('tipoPregunta', 255);
            });
        }

        // Insertar datos si la tabla está vacía (camelCase en columnas)
        $count = DB::table($tableCamel)->count();
        if ($count === 0) {
            DB::table($tableCamel)->insert([
                ['tipoPregunta' => 'Párrafo'],
                ['tipoPregunta' => 'Varias opciones'],
            ]);
        }
    }

    public function down(): void
    {
        // No revertir para evitar pérdida de datos
    }
};
