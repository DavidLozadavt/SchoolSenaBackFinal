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
    Schema::create('asignacionParticipantes', function (Blueprint $table) {
      $table->id();

      $table->foreignId('idGrupo')->nullable()->references('id')->on('gruposChat');

      $table->unsignedInteger('idActivationCompanyUser')->nullable();
      $table->foreign('idActivationCompanyUser')->references('id')->on('activation_company_users');

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
    Schema::dropIfExists('asignacionParticipantes');
  }
};
