<?php

namespace Database\Seeders;

use App\Http\Middleware\Permisions;
use App\Models\ActivationCompanyUser;
use App\Models\Person;
use App\Models\User;
use App\Permission\PermissionConst;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $vt = new Role();
        $vt->name = "Admin";
        $vt->company_id = 1;
        $vt->save();

        $rapidoTambo = new Role();
        $rapidoTambo->name = "Admin";
        $rapidoTambo->company_id = 2;
        $rapidoTambo->save();

        $vt->syncPermissions([
            PermissionConst::GESTION_ROL_PERMISOS,
            PermissionConst::GESTION_ROLES,
            PermissionConst::GESTION_TIPO_CONTRATO,
            PermissionConst::GESTION_USUARIO,
            PermissionConst::GESTION_PROCESOS,
            PermissionConst::GESTION_TIPO_CONTRATO,
            PermissionConst::GESTION_MEDIO_PAGO,
            PermissionConst::GESTION_TIPO_PAGO,
            PermissionConst::GESTION_TIPO_TRANSACCION,
            PermissionConst::GESTION_TIPO_DOCUMENTOS,
            PermissionConst::GESTION_CONTRATACION,
            PermissionConst::GESTION_CONTRATOS,
            PermissionConst::GESTION_PAGOS_CONTRATOS,
            PermissionConst::GESTION_LABORAL,
            PermissionConst::GESTION_PAGOS_ADICIONALES,
            PermissionConst::GESTION_COMPRAS,
            PermissionConst::GESTION_PAGOS_COMPRAS,
            PermissionConst::GESTION_CHAT,


        ]);

        $rapidoTambo->syncPermissions([
            PermissionConst::GESTION_TIPO_CONTRATO,
            PermissionConst::GESTION_USUARIO,
        ]);

        $emailAdmin = "admin@gmail.com";
        Person::factory()
            ->hasUsuario(1, ['email' => $emailAdmin])
            ->create([
                'email' => $emailAdmin
            ]);


        $activation = ActivationCompanyUser::factory()->create([
            'company_id' => 1,
            'user_id' => 1,
            'state_id' => 1
        ]);

        $activation->assignRole($vt);

        $activation = ActivationCompanyUser::factory()->create([
            'company_id' => 2,
            'user_id' => 1,
            'state_id' => 1
        ]);

        $activation->assignRole($rapidoTambo);
    }
}
