<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->unsignedBigInteger('idCentroFormacion')->nullable()->after('centroId');

            $table->foreign('idCentroFormacion')
                  ->references('id')
                  ->on('centroFormacion')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->dropForeign(['idCentroFormacion']);
            $table->dropColumn('idCentroFormacion');
        });
    }
};
