<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seguimiento_aspirantes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('celular');
            $table->string('correo')->nullable();
            
            $table->string('centro_formacion');
            $table->string('programa');
            $table->string('ficha');
            
            $table->date('fecha_registro_excel')->nullable();
            
            $table->string('estado')->default('Pendiente');
            $table->string('respuesta')->nullable();
            $table->dateTime('fecha_respuesta')->nullable();
            $table->string('wa_message_id')->nullable();      // ID del mensaje enviado (para correlacionar statuses)
            $table->string('estado_envio')->nullable();       // sent / delivered / read / failed
            $table->string('error_envio')->nullable();        // motivo si el estado es failed
            $table->dateTime('ultimo_envio')->nullable();
            $table->integer('cantidad_envios')->default(0);
            
            $table->timestamps();
        });

        // Registrar el permiso y asociarlo al rol Admin
        try {
            $permission = Permission::firstOrCreate([
                'name' => 'GESTION_SEGUIMIENTO_ASPIRANTES'
            ], [
                'description' => 'Módulo de seguimiento de aspirantes'
            ]);

            $roles = Role::where('name', 'Admin')->get();
            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }
        } catch (\Exception $e) {
            // Log or ignore if table does not exist yet (e.g. running fresh in other environments)
            Log::warning('No se pudo crear/asignar el permiso GESTION_SEGUIMIENTO_ASPIRANTES: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seguimiento_aspirantes');

        try {
            $permission = Permission::where('name', 'GESTION_SEGUIMIENTO_ASPIRANTES')->first();
            if ($permission) {
                $permission->delete();
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
