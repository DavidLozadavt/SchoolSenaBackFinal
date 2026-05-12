<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('compromisoActa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idacta');
            $table->text('actividad')->collation('utf8mb4_unicode_ci');
            $table->date('fecha');
            $table->text('responsable')->collation('utf8mb4_unicode_ci');
            $table->string('firma', 255)->collation('utf8mb4_unicode_ci')->nullable();
            $table->timestamps();

            // Clave foránea
            $table->foreign('idacta')
                ->references('id')
                ->on('acta');
        });
    }

    public function down()
    {
        Schema::dropIfExists('compromisoActa');
    }
};