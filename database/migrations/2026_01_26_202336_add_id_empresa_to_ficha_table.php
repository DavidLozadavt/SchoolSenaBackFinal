<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ficha', function (Blueprint $table) {
            $table->unsignedInteger('idRegional')->nullable()->after('idSede');

            $table->index('idRegional');

            $table->foreign('idRegional')
                  ->references('id')
                  ->on('empresa')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ficha', function (Blueprint $table) {
            $table->dropForeign(['idRegional']);
            $table->dropIndex(['idRegional']);
            $table->dropColumn('idRegional');
        });
    }
};