<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('infraestructura', function (Blueprint $table) {
            $table->foreignId('idTipoInfraestructura')
                ->nullable()
                ->after('idSede')
                ->constrained('tiposinfraestructura')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('infraestructura', function (Blueprint $table) {
            $table->dropForeign(['idTipoInfraestructura']);
            $table->dropColumn('idTipoInfraestructura');
        });
    }
};
