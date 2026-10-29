<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wompiAuditoriaPago', function (Blueprint $table) {
            if (!Schema::hasColumn('wompiAuditoriaPago', 'idFactura')) {
                $table->unsignedBigInteger('idFactura')->nullable();
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'idEmpresa')) {
                $table->unsignedBigInteger('idEmpresa')->nullable();
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'referencia')) {
                $table->string('referencia')->nullable();
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'transactionId')) {
                $table->string('transactionId')->nullable()->unique();
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'amount')) {
                $table->decimal('amount', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'currency')) {
                $table->string('currency', 3)->default('COP');
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'status')) {
                $table->string('status', 32)->default('PENDIENTE');
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'provider')) {
                $table->string('provider', 32)->default('WOMPI');
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'payload')) {
                $table->text('payload')->nullable();
            }
            if (!Schema::hasColumn('wompiAuditoriaPago', 'observacion')) {
                $table->string('observacion', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wompiAuditoriaPago', function (Blueprint $table) {
            $table->dropColumn([
                'idFactura',
                'idEmpresa',
                'referencia',
                'transactionId',
                'amount',
                'currency',
                'status',
                'provider',
                'payload',
                'observacion',
            ]);
        });
    }
};
