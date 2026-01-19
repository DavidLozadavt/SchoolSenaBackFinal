<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedBigInteger('idRegional')
                ->nullable()
                ->after('digitoVerificacion');

            $table->foreign('idRegional')
                ->references('id')
                ->on('regional')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropForeign(['idRegional']);
            $table->dropColumn('idRegional');
        });
    }
};
