<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('formularios', function (Blueprint $table) {
            if (!Schema::hasColumn('formularios', 'fechaInicio')) {
                $table->dateTime('fechaInicio')->nullable()->after('requiereAutenticacion');
            }
            if (!Schema::hasColumn('formularios', 'limiteRespuestas')) {
                $table->unsignedInteger('limiteRespuestas')->nullable()->after('fechaLimite');
            }
            if (!Schema::hasColumn('formularios', 'mensajeCierre')) {
                $table->text('mensajeCierre')->nullable()->after('limiteRespuestas');
            }
            if (!Schema::hasColumn('formularios', 'permiteMultiplesRespuestas')) {
                $table->boolean('permiteMultiplesRespuestas')->default(true)->after('mensajeCierre');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('formularios', function (Blueprint $table) {
            $table->dropColumn(['fechaInicio', 'limiteRespuestas', 'mensajeCierre', 'permiteMultiplesRespuestas']);
        });
    }
};
