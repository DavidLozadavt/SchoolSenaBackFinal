<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->string('supervisorContrato')->nullable()->default(null)->after('siif');
            $table->string('cargoSupervisor')->nullable()->default(null)->after('supervisorContrato');
        });
    }

    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->dropColumn(['supervisorContrato', 'cargoSupervisor']);
        });
    }
};
