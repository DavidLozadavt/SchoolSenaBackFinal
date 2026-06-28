<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('persona_eduexce') && !Schema::hasColumn('persona_eduexce', 'external_school_id')) {
            Schema::table('persona_eduexce', function (Blueprint $table) {
                $table->unsignedBigInteger('external_school_id')->nullable()->after('id_empresa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('persona_eduexce', 'external_school_id')) {
            Schema::table('persona_eduexce', function (Blueprint $table) {
                $table->dropColumn('external_school_id');
            });
        }
    }
};
