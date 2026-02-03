<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('otps')) {
            Schema::create('otps', function (Blueprint $table) {
                $table->id();
                $table->string('identifier'); // email
                $table->string('token', 6); // código de 6 dígitos
                $table->integer('validity')->default(10); // minutos de validez
                $table->boolean('valid')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable(); // Agregado
                
                // Índices para mejorar rendimiento
                $table->index(['identifier', 'valid']);
                $table->index('created_at');
            });
        } else {
            // Si la tabla ya existe, agregar columnas faltantes
            
            // 1. Agregar updated_at si no existe
            if (!Schema::hasColumn('otps', 'updated_at')) {
                Schema::table('otps', function (Blueprint $table) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                });
            }
            
            // 2. Verificar que created_at tenga valor por defecto
            if (Schema::hasColumn('otps', 'created_at')) {
                // No hacemos nada, solo asegurar que exista
            } else {
                Schema::table('otps', function (Blueprint $table) {
                    $table->timestamp('created_at')->useCurrent();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};