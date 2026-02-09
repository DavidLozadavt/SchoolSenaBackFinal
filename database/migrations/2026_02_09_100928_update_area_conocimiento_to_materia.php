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
        Schema::table('materia', function (Blueprint $table) {

            if (Schema::hasColumn('materia', 'idAreaConocimiento')) {
                $table->dropColumn('idAreaConocimiento');
            }
        });

        Schema::table('materia', function (Blueprint $table) {

            $table->unsignedBigInteger('idAreaConocimiento')->nullable();

            $table->foreign('idAreaConocimiento')
                ->references('id')
                ->on('area_conocimiento');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('materia', function (Blueprint $table) {
            //
        });
    }
};
