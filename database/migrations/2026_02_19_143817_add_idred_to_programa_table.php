<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::table('programa', function (Blueprint $table) {
        $table->foreignId('idRed')
              ->nullable()
              ->constrained('red')
              ->nullOnDelete()
              ->cascadeOnUpdate();
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
{
    Schema::table('programa', function (Blueprint $table) {
        $table->dropForeign(['idRed']);
        $table->dropColumn('idRed');
    });
}
};
