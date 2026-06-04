<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_inscripcion', function (Blueprint $table) {
            $table->unsignedBigInteger('idFormularioRespuesta')->nullable()->after('idFactura');
            $table->index('idFormularioRespuesta', 'seg_insc_form_resp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('seguimiento_inscripcion', function (Blueprint $table) {
            $table->dropIndex('seg_insc_form_resp_idx');
            $table->dropColumn('idFormularioRespuesta');
        });
    }
};
