<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivosRap', function (Blueprint $table) {
            $table->id();

            $table->date('fechaReporte');

            $table->unsignedBigInteger('idPrograma')->nullable();
            $table->unsignedBigInteger('idFicha')->nullable();
            $table->unsignedBigInteger('idGrado')->nullable();
            $table->unsignedBigInteger('idSede')->nullable();

            $table->timestamps();

            $table->unsignedInteger('idUser')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivosRap');
    }
};
