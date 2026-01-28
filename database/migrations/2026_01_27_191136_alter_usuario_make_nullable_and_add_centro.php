<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
{
    DB::statement('ALTER TABLE usuario DROP FOREIGN KEY usuario_idpersona_foreign');

    DB::statement('ALTER TABLE usuario MODIFY idpersona INT UNSIGNED NULL');

    DB::statement('ALTER TABLE usuario 
        ADD CONSTRAINT usuario_idpersona_foreign 
        FOREIGN KEY (idpersona) REFERENCES persona(id) 
        ON DELETE SET NULL
    ');

    Schema::table('usuario', function (Blueprint $table) {
        $table->unsignedBigInteger('idCentroFormacion')->nullable()->after('idpersona');

        $table->foreign('idCentroFormacion', 'usuario_idcentroformacion_foreign')
              ->references('id')
              ->on('centrosformacion')
              ->nullOnDelete();
    });
}


    public function down()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign('usuario_idcentroformacion_foreign');
            $table->dropColumn('idCentroFormacion');
        });

        DB::statement('ALTER TABLE usuario DROP FOREIGN KEY usuario_idpersona_foreign');
        DB::statement('ALTER TABLE usuario MODIFY idpersona INT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE usuario 
        ADD CONSTRAINT usuario_idpersona_foreign 
        FOREIGN KEY (idpersona) REFERENCES persona(id)
    ');
    }
};
