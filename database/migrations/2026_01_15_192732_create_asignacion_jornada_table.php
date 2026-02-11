<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignacionJornada', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idAsignacion')
                  ->constrained('aperturarprograma');

            $table->foreignId('idJornada')
                  ->constrained('jornadas');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_jornada');
    }
};
