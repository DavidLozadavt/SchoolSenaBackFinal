<?php

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
        if (!Schema::hasTable('otps')) {
            Schema::create('otps', function (Blueprint $table) {
                $table->id();
                $table->string('identifier');
                $table->string('token', 6);
                $table->integer('validity')->default(10); 
                $table->boolean('valid')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable(); 
             
                $table->index(['identifier', 'valid']);
                $table->index('created_at');
            });
        } else {

            if (!Schema::hasColumn('otps', 'updated_at')) {
                Schema::table('otps', function (Blueprint $table) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                });
            }

            if (Schema::hasColumn('otps', 'created_at')) {
            } else {
                Schema::table('otps', function (Blueprint $table) {
                    $table->timestamp('created_at')->useCurrent();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};