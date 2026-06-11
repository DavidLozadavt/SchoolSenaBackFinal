<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('aperturarprograma', function (Blueprint $table) {
            $table->string('nombre')->nullable()->after('id');
            $table->unsignedBigInteger('idTipoGrado')->nullable()->after('nombre');

            $table->foreign('idTipoGrado')
                  ->references('id')
                  ->on('tipoGrado');
        });
    }

    public function down()
    {
        Schema::table('aperturarprograma', function (Blueprint $table) {
            $table->dropForeign(['idTipoGrado']);
            $table->dropColumn(['nombre', 'idTipoGrado']);
        });
    }
};