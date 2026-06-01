<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdEventoToItemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('item', 'idEvento')) {
            Schema::table('item', function (Blueprint $table) {
                $table->dropColumn('idEvento');
            });
        }

        Schema::table('item', function (Blueprint $table) {
            $table->unsignedInteger('idEvento')->nullable()->after('id');
            $table->foreign('idEvento')->references('idEvento')->on('evento')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('item', function (Blueprint $table) {
            $table->dropForeign(['idEvento']);
            $table->dropColumn('idEvento');
        });
    }
}
