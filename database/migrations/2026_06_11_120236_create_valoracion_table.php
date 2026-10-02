<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('valoracion', function (Blueprint $table) {
            $table->id(); // bigint AUTO_INCREMENT (id)
            $table->string('nombreValoracion', 100)->nullable();
            $table->enum('nivelAcademico', ['PREESCOLAR', 'PRIMARIA', 'BACHILLERATO', 'SUPERIOR'])->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('valoracion');
    }
};