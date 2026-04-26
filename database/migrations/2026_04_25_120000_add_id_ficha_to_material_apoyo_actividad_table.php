<?php

use App\Models\MaterialApoyoActividad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende materialApoyoActividad (misma tabla que el material por actividad):
 * idFicha nullable enlaza el registro a ficha.id para material global de esa ficha.
 * Los campos titulo, descripcion, urlDocumento, urlAdicional, idMateria se reutilizan igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = (new MaterialApoyoActividad())->getTable();
        if (!Schema::hasTable($tableName) || !Schema::hasTable('ficha') || Schema::hasColumn($tableName, 'idFicha')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('idFicha')->nullable()->after('idMateria');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreign('idFicha')->references('id')->on('ficha')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $tableName = (new MaterialApoyoActividad())->getTable();
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'idFicha')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['idFicha']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('idFicha');
        });
    }
};
