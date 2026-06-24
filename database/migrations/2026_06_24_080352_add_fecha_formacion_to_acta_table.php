<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('acta', function (Blueprint $table) {
            $table->date('fechaInicialFormacion')->nullable()->after('fecha');
            $table->date('fechaFinalFormacion')->nullable()->after('fechaInicialFormacion');
        });
    }

    public function down()
    {
        Schema::table('acta', function (Blueprint $table) {
            $table->dropColumn(['fechaInicialFormacion', 'fechaFinalFormacion']);
        });
    }
};