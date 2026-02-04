<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('programa')) {
            return;
        }
        if (Schema::hasColumn('programa', 'documento')) {
            return;
        }

        Schema::table('programa', function (Blueprint $table) {
            $table->string('documento', 500)->nullable()->after('descripcionPrograma');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('programa')) {
            return;
        }
        if (!Schema::hasColumn('programa', 'documento')) {
            return;
        }

        Schema::table('programa', function (Blueprint $table) {
            $table->dropColumn('documento');
        });
    }
};
