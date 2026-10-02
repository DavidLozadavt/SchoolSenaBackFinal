<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contrato', 'supervisorContrato')) {
            Schema::table('contrato', function (Blueprint $table) {
                $table->string('supervisorContrato', 255)->nullable();
            });
        }
        if (! Schema::hasColumn('contrato', 'cargoSupervisor')) {
            Schema::table('contrato', function (Blueprint $table) {
                $table->string('cargoSupervisor', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            if (Schema::hasColumn('contrato', 'cargoSupervisor')) {
                $table->dropColumn('cargoSupervisor');
            }
            if (Schema::hasColumn('contrato', 'supervisorContrato')) {
                $table->dropColumn('supervisorContrato');
            }
        });
    }
};
