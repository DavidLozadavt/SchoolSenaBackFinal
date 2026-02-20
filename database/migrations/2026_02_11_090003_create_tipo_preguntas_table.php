<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tipo_preguntas')) {
            return;
        }
        Schema::create('tipo_preguntas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipoPregunta', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_preguntas');
    }
};
