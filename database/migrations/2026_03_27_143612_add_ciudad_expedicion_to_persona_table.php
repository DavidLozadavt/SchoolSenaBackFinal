<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->unsignedInteger('ciudadExpedicion')->nullable()->after('idCiudadUbicacion');

            $table->foreign('ciudadExpedicion')
                ->references('id')
                ->on('ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->dropForeign(['ciudadExpedicion']);
            $table->dropColumn('ciudadExpedicion');
        });
    }
};
