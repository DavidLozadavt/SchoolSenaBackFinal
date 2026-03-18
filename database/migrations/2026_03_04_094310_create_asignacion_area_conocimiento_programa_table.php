<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * antes de ejecutar la migracion ejecuta este sql
     * ALTER TABLE db_sena_school.area_conocimiento DROP FOREIGN KEY area_conocimiento_idniveleducativo_foreign;
     * ALTER TABLE db_sena_school.area_conocimiento DROP INDEX area_conocimiento_idniveleducativo_foreign;
     * ALTER TABLE db_sena_school.area_conocimiento DROP COLUMN idNivelEducativo;
     * 
     * @return void
     */
    public function up()
    {
        Schema::create('asignacionAreaConocimientoPrograma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idPrograma')->constrained('programa')->bigIntegerUnsigned();
            $table->foreignId('idAreaConocimiento')->constrained('area_conocimiento')->bigIntegerUnsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asignacionAreaConocimientoPrograma');
    }
};
