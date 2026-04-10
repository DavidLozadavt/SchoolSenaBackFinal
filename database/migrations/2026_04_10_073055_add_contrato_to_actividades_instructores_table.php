<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividadesInstructores', function (Blueprint $table) {
            $table->unsignedInteger('idContrato')->after('idRmi');

            $table->foreign('idContrato')
                ->references('id')
                ->on('contrato');
        });
    }

    public function down(): void
    {
        Schema::table('actividadesInstructores', function (Blueprint $table) {
            $table->dropForeign(['idContrato']);
            $table->dropColumn('idContrato');
        });
    }
};
