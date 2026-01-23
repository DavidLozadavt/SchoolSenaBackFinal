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
        Schema::table('contrato', function (Blueprint $table) {
            $table->unsignedInteger('idNivelEducativo')->nullable()->after('idArea');
            $table->foreign('idNivelEducativo')->references('id')->on('nivelEducativo')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->dropForeign(['idNivelEducativo']);
            $table->dropColumn('idNivelEducativo');
        });
    }
};
