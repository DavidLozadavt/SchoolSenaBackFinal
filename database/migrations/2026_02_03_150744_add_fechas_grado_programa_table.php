<?php

use App\Enums\EstadoGradoPrograma;
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
        Schema::table('gradoPrograma',function (Blueprint $table) {
            $table->date('fechaInicio')->nullable();
            $table->date('fechaFin')->nullable();
            $table->enum('estado', EstadoGradoPrograma::getValues())->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
