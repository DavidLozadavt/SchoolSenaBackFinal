<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cortes')) {
            Schema::create('cortes', function (Blueprint $table) {
                $table->id();
                $table->integer('numero');
                $table->date('fechaInicial');
                $table->date('fechaFinal');
                $table->integer('porcentaje');
                $table->unsignedBigInteger('idApertura');
                $table->index('idApertura');
            });
        }

        if (Schema::hasTable('ficha') && !Schema::hasColumn('ficha', 'idGrado')) {
            Schema::table('ficha', function (Blueprint $table) {
                $table->unsignedBigInteger('idGrado')->nullable()->after('idRegional');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cortes');
        if (Schema::hasTable('ficha') && Schema::hasColumn('ficha', 'idGrado')) {
            Schema::table('ficha', function (Blueprint $table) {
                $table->dropColumn('idGrado');
            });
        }
    }
};
