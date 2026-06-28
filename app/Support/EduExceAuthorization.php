<?php



namespace App\Support;



use App\Models\Company;

use App\Permission\PermissionConst;

use App\Util\KeyUtil;

use Illuminate\Support\Collection;



class EduExceAuthorization

{

    public static function userPermissions(): Collection

    {

        return collect(KeyUtil::permissions())->map(fn ($p) => strtoupper((string) $p));

    }



    /**

     * Instituciones EduExce con permiso MODULO_ICFES.

     * Bloquea usuarios/empresas de centros de formación SENA (regional o centro).

     */

    public static function canModuloIcfes(): bool

    {

        $user = auth('api')->user();

        if (!$user) {

            return false;

        }



        if (!empty($user->idCentroFormacion)) {

            return false;

        }



        if (!self::userPermissions()->contains(PermissionConst::MODULO_ICFES)) {

            return false;

        }



        $companyId = (int) KeyUtil::idCompany();

        if ($companyId <= 0) {

            return false;

        }



        // Regional SENA: la empresa tiene centros de formación hijos.

        if (self::empresaEsRegionalSena($companyId)) {

            return false;

        }



        return true;

    }



    /** Admin VT: licencias por institución EduExce. */

    public static function canLicenciaIcfes(): bool

    {

        return self::userPermissions()->contains(PermissionConst::LICENCIA_ICFES);

    }



    public static function vtRoleNames(): array

    {

        return ['ADMINISTRADOR VT', 'ADMIN VT', 'administradorVT'];

    }



    private static function empresaEsRegionalSena(int $companyId): bool

    {

        $company = Company::find($companyId);



        return $company !== null && $company->centrosFormacion()->exists();

    }

}

