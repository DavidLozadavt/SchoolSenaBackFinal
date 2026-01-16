<?php

namespace Database\Seeders;

use App\Permission\PermissionConst;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->savePermission(PermissionConst::GESTION_ROLES, "Gestión de roles");
        $this->savePermission(PermissionConst::GESTION_ROL_PERMISOS, "Gestión permisos del rol");
        $this->savePermission(PermissionConst::GESTION_USUARIO, "Gestión de usuarios");
        $this->savePermission(PermissionConst::GESTION_TIPO_CONTRATO, "Gestión de tipos de contrato");
        $this->savePermission(PermissionConst::GESTION_PROCESOS, "Gestión de procesos");
        $this->savePermission(PermissionConst::GESTION_TIPO_DOCUMENTOS, "Gestión de tipos de documento");
        $this->savePermission(PermissionConst::GESTION_MEDIO_PAGO, "Gestión medios de pago");
        $this->savePermission(PermissionConst::GESTION_TIPO_PAGO, "Gestión de tipos de pago");
        $this->savePermission(PermissionConst::GESTION_TIPO_TRANSACCION, "Gestión de tipos de transacciòn");
        $this->savePermission(PermissionConst::GESTION_CONTRATACION, "Gestión de contrataciòn");
        $this->savePermission(PermissionConst::GESTION_CONTRATOS, "Gestión de contratos");
        $this->savePermission(PermissionConst::GESTION_PAGOS_CONTRATOS, "Gestión para pagos de contratos");
        $this->savePermission(PermissionConst::GESTION_LABORAL, "Gestión de labores");
        $this->savePermission(PermissionConst::GESTION_PAGOS_ADICIONALES, "Gestión de pagos adicionales");
        $this->savePermission(PermissionConst::GESTION_COMPRAS, "Gestión de compras");
        $this->savePermission(PermissionConst::GESTION_PAGOS_COMPRAS, "Gestión de pagos de compras");
        $this->savePermission(PermissionConst::GESTION_PLANES, "Gestión de planes");
        $this->savePermission(PermissionConst::GESTION_PRODUCTOS_EMPRESARIALES, "Gestión de productos empresariales");
        $this->savePermission(PermissionConst::GESTION_CONEXIONES, "Gestión de conexiones");
        $this->savePermission(PermissionConst::GESTION_SOLICITUDES_PRODUCTOS, "Gestión de solicitudes de productos");
        $this->savePermission(PermissionConst::GESTION_CUENTAS_PENDIENTES, "Gestión de cuentas pendientes");
        $this->savePermission(PermissionConst::GESTION_APORTES_SOCIOS, "Gestión de aportes socios");
        $this->savePermission(PermissionConst::GESTION_CHAT, "Gestión de mensajes");
        $this->savePermission(PermissionConst::GESTION_NOMINA, "Gestión de nominas");
        $this->savePermission(PermissionConst::GESTION_PUNTO_VENTAS, "Gestión de punto de venta");


        
    }

    private function savePermission($name, $description)
    {
        $permission = new Permission();
        $permission->name = $name;
        $permission->description = $description;
        $permission->save();
    }
}
