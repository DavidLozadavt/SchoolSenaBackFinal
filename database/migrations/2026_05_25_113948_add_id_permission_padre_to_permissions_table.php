<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Agrega la columna (nullable y mismo tipo que 'id')
            $table->bigInteger('idPermissionPadre')
                  ->unsigned()
                  ->nullable()
                  ->after('description');

            // Foreign key self-referencing
            $table->foreign('idPermissionPadre')
                  ->references('id')
                  ->on('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['idPermissionPadre']);
            $table->dropColumn('idPermissionPadre');
        });
    }
};