<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHermanoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hermano', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 240);
            $table->string('celularContacto', 100)->nullable();
            $table->integer('edad')->nullable();
            $table->string('nombreContactoF', 240)->nullable();
            $table->string('celularContactoF', 240)->nullable();
            $table->decimal('pago', 12, 2)->nullable();
            $table->decimal('saldo', 12, 2)->nullable();
            $table->string('formaPago', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('celularEmergencia', 100)->nullable();
            $table->string('parentesco', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->string('qr_token', 255)->unique()->nullable();
            $table->string('qr_path', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hermano');
    }
}
