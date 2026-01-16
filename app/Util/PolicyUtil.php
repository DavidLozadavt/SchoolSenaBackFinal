<?php

namespace App\Util;

class PolicyUtil
{

    /**
     * Verify user is admin
     *
     * @param User $user
     * @return boolean
     */
    public static function isAdmin()
    {
        $idCompany = KeyUtil::idCompany();
        // if ($idCompany != 1) {
        //     return false;
        // } // Se comenta porque cada admin de cada institucion deberia poder gestionar los permisos con roles

        return in_array("ADMIN", KeyUtil::roles());
    }

    public static function hasPermission(array $expected_permissions)
    {
        $permissions = KeyUtil::permissions();
        $has_permission = array_intersect($permissions, $expected_permissions);
        return !empty($has_permission);
    }
}
