<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ficha', function (Blueprint $table) {
            $table->unsignedBigInteger('idAprendizVocero')
                  ->nullable()
                  ->after('documento');

            $table->unsignedBigInteger('idAprendizSuplente')
                  ->nullable()
                  ->after('idAprendizVocero');

            /* Índices */
            $table->index('idAprendizVocero');
            $table->index('idAprendizSuplente');

            /* Claves foráneas */
            $table->foreign('idAprendizVocero')
                  ->references('id')->on('matricula');

            $table->foreign('idAprendizSuplente')
                  ->references('id')->on('matricula');
        });
    }

    public function down(): void
    {
        Schema::table('ficha', function (Blueprint $table) {
            $table->dropForeign(['idAprendizVocero']);
            $table->dropForeign(['idAprendizSuplente']);

            $table->dropIndex(['idAprendizVocero']);
            $table->dropIndex(['idAprendizSuplente']);

            $table->dropColumn([
                'idAprendizVocero',
                'idAprendizSuplente',
            ]);
        });
    }
};
