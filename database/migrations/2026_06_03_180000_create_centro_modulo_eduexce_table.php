<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('centro_modulo_eduexce')) {
            Schema::create('centro_modulo_eduexce', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_empresa');
                $table->unsignedBigInteger('id_centro_formacion');
                $table->unsignedBigInteger('id_institucion_eduexce')->nullable();
                $table->boolean('activo')->default(false);
                $table->date('fecha_vigencia_fin')->nullable();
                $table->timestamp('provisionado_at')->nullable();
                $table->text('ultimo_error')->nullable();
                $table->timestamps();

                $table->unique(['id_empresa', 'id_centro_formacion']);
                $table->foreign('id_empresa')->references('id')->on('empresa')->onDelete('cascade');
                $table->foreign('id_centro_formacion')->references('id')->on('centroFormacion')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_modulo_eduexce');
    }
};
