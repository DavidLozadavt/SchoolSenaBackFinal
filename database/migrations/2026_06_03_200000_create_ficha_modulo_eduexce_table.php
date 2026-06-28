<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ficha_modulo_eduexce')) {
            Schema::create('ficha_modulo_eduexce', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_ficha');
                $table->unsignedBigInteger('id_empresa');
                $table->unsignedBigInteger('id_centro_formacion')->nullable();
                $table->boolean('activo')->default(false);
                $table->timestamps();

                $table->unique(['id_ficha', 'id_empresa', 'id_centro_formacion'], 'ficha_eduexce_unique');
                $table->index(['id_empresa', 'id_centro_formacion', 'activo'], 'ficha_eduexce_centro_activo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_modulo_eduexce');
    }
};
