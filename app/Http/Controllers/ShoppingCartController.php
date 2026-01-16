<?php

use App\Enums\StatusCartType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shoppingCart', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idUser')->nullable();
            $table->foreign('idUser')->references('id')->on('usuario');

            $table->longText('idDetallesProducto')->nullable()->check('json_valid(idDetallesProducto)');
            $table->string('cantidad', 100)->nullable();

            $table->enum('estado', StatusCartType::getValues())->default(StatusCartType::PENDIENTE);
            $table->string('nTransaccion')->nullable();
            $table->string('tipoTransaccion')->nullable()->comment('Nombre de transacción método de pago');

            $table->text('observacion')->nullable();

            $table->foreignId('idUbicacion')->nullable()->references('id')->on('destinos');

            $table->string('nombreRecibe')->nullable();
            
            $table->foreignId('idTercero')->nullable()->references('id')->on('tercero');

            $table->float('valorUtilidad')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoppingcart');
    }
};
