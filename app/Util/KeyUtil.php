<?php

namespace App\Util;

use App\Models\ActivationCompanyUser;
use App\Models\Contract;
use App\Models\FacturaElectronica;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class KeyUtil
{

    public static function idCompany()
    {
        $token = JWTAuth::getToken();
        $payload = JWTAuth::getPayload($token)->toArray();
        return $payload['idCompany'];
    }

    public static function roles()
    {
        $token = JWTAuth::getToken();
        $payload = JWTAuth::getPayload($token)->toArray();
        return $payload['roles'];
    }

    public static function permissions()
    {
        $token = JWTAuth::getToken();
        $payload = JWTAuth::getPayload($token)->toArray();
        return $payload['permissions']->keys();
    }

    public static function user()
    {
        return User::with(['persona.contrato', 'persona.ubicacion', 'activationCompanyUsers', 'persona.ciudad', 'persona.ciudadNac.departamento', 'persona.ciudadUbicacion.departamento', 'persona.tipoIdentificacion'])->find(auth()->id());
    }

    public static function getUserCompany()
    {
        $token = JWTAuth::getToken();
        $payload = JWTAuth::getPayload($token)->toArray();
        $idCompany = $payload['idCompany'];

        $idUser = ActivationCompanyUser::where('idCompany', $idCompany)->value('idUser');

        return $idUser;
    }

    /**
     * Get last contract active
     * @return Contract|object|\Illuminate\Database\Eloquent\Model|null
     */
    public static function lastContractActive(): Contract
    {
        $contract = Contract::where('idpersona', self::user()->idpersona)
            ->where('idEstado', 1)
            ->latest()
            ->first();

        return $contract;
    }

       
}
