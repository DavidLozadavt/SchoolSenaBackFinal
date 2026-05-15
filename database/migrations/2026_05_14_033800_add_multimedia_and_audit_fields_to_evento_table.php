<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMultimediaAndAuditFieldsToEventoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('evento', function (Blueprint $table) {
            // Campos para auditoría y organización
            if (!Schema::hasColumn('evento', 'idCompany')) {
                $table->unsignedBigInteger('idCompany')->nullable()->after('idEvento');
            }
            if (!Schema::hasColumn('evento', 'idUser')) {
                $table->unsignedBigInteger('idUser')->nullable()->after('idCompany');
            }
            
            // Vínculo con Multimedia
            if (!Schema::hasColumn('evento', 'idGrupoMultimedia')) {
                $table->unsignedBigInteger('idGrupoMultimedia')->nullable()->after('idUser');
            }

            // Timestamps si no los tiene
            if (!Schema::hasColumn('evento', 'created_at')) {
                $table->timestamps();
            }

            // Hora final para estados dinámicos
            if (!Schema::hasColumn('evento', 'hora_final')) {
                $table->time('hora_final')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('evento', function (Blueprint $table) {
            $table->dropColumn(['idCompany', 'idUser', 'idGrupoMultimedia']);
            if (Schema::hasColumn('evento', 'hora_final')) {
                $table->dropColumn('hora_final');
            }
            $table->dropTimestamps();
        });
    }
}
