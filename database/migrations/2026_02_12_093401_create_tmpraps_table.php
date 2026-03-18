<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tmpRaps', function (Blueprint $table) {
            $table->id();

            $table->string('tipoIde', 30)->nullable();
            $table->string('identificacion', 100)->nullable();
            $table->string('nombre', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('estado', 100)->nullable();

            $table->text('competencia')->nullable();
            $table->text('rap')->nullable();

            $table->string('evaluacion', 100)->nullable();
            $table->dateTime('fechaEvaluacion')->nullable();

            $table->text('responsableEvaluacion')->nullable();

            $table->boolean('bandera')->default(false);

            $table->unsignedInteger('idUser')->nullable();
            $table->unsignedBigInteger('idPrograma')->nullable();

            $table->timestamps();

            // Foreign key
            $table->foreign('idUser')
                ->references('id')
                ->on('usuario')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tmpRaps');
    }
};
