<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('conclusionActa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idacta');
            $table->text('conclusion')->collation('utf8mb4_unicode_ci');
            $table->timestamps();

            // Clave foránea
            $table->foreign('idacta')
                ->references('id')
                ->on('acta');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conclusionActa');
    }
};