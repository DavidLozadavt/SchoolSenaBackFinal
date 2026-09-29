<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Permission\PermissionConst;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tabla local y autónoma para almacenar las credenciales de WhatsApp Cloud API (Meta).
 *
 * Este módulo NO depende de ningún backend externo (Telecom Manager / Tax Belalcázar).
 * Todas las credenciales viven en este proyecto y el envío llama directamente a
 * https://graph.facebook.com/{version}/{phone_number_id}/messages
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('telecom_configs', function (Blueprint $table) {
            $table->id();

            // Proveedor del canal (meta / whatsapp_cloud)
            $table->string('provider')->default('meta');

            // Estado del canal de WhatsApp
            $table->boolean('whatsappEnabled')->default(true);

            // Nombre descriptivo de la configuración (ej. "WhatsApp Institucional")
            $table->string('nombre')->nullable();

            // Credenciales de WhatsApp Cloud API (Meta)
            $table->text('accessToken');                       // Token permanente / de sistema
            $table->string('phoneNumberId');                   // ID del número de teléfono
            $table->string('businessAccountId')->nullable();   // WABA ID
            $table->string('appId')->nullable();               // App ID de Meta
            $table->string('verifyToken');                     // Token de verificación del webhook
            $table->string('appSecret')->nullable();           // Opcional: para validar firma X-Hub-Signature-256
            $table->string('webhookUrl')->nullable();          // URL pública del callback registrada en Meta

            // Versión del Graph API a utilizar (por defecto v23.0)
            $table->string('graphVersion')->default('v23.0');

            // Solo una configuración activa a la vez
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });

        // Registrar el permiso y asignarlo al rol Admin (mismo mecanismo que los demás módulos)
        try {
            $permission = Permission::firstOrCreate([
                'name' => PermissionConst::GESTION_TELECOM_CONFIG
            ], [
                'description' => 'Configuracion Whatsapp',
                'path'        => '/telecom-config',
                'icon'        => 'whatsapp',
            ]);

            // Asegura path/icon aunque el permiso ya existiera (para el menú dinámico)
            $permission->forceFill([
                'path' => '/telecom-config',
                'icon' => 'whatsapp',
            ])->save();

            // El rol super-administrador de este proyecto es 'ADMINISTRADOR VT'
            $roles = Role::where('name', 'ADMINISTRADOR VT')->get();
            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Exception $e) {
            Log::warning('No se pudo crear/asignar el permiso GESTION_TELECOM_CONFIG: ' . $e->getMessage());
        }
    }

    public function down()
    {
        Schema::dropIfExists('telecom_configs');

        try {
            $permission = Permission::where('name', PermissionConst::GESTION_TELECOM_CONFIG)->first();
            if ($permission) {
                $permission->delete();
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
