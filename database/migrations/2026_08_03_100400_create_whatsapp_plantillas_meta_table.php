<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo local de las plantillas de Meta (WhatsApp Cloud API).
 *
 * Tabla NUEVA. La tabla existente `whatsappPlantillas` (plantilla oficial usada
 * por el envío de campañas) NO se modifica ni se elimina: sigue siendo la fuente
 * del flujo actual de envío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsappPlantillasMeta', function (Blueprint $table) {
            $table->id();
            $table->string('metaTemplateId')->nullable()->unique();
            $table->string('nombre');
            // UTILITY | MARKETING | AUTHENTICATION
            $table->string('categoria')->default('UTILITY');
            $table->string('idioma')->default('es');
            // APPROVED | PENDING | REJECTED | PAUSED | DISABLED | IN_REVIEW
            $table->string('estadoMeta')->default('PENDING');
            $table->text('contenido')->nullable();
            $table->text('encabezado')->nullable();
            $table->text('pie')->nullable();
            $table->json('variablesEjemplo')->nullable();
            $table->json('botones')->nullable();
            $table->json('respuestaMeta')->nullable();
            $table->text('motivoRechazo')->nullable();
            $table->unsignedBigInteger('creadoPorUserId')->nullable();
            $table->string('creadoPorNombre')->nullable();
            $table->dateTime('fechaAprobacion')->nullable();
            $table->dateTime('ultimaSincronizacion')->nullable();
            $table->timestamps();

            $table->unique(['nombre', 'idioma']);
            $table->index('estadoMeta');
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsappPlantillasMeta');
    }
};
