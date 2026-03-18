<?php

namespace App\Traits;

use App\Models\Notificacion;
use App\Models\Status;
use App\Util\KeyUtil;


/**
 *
 */
trait UtilNotification
{
    protected function sendNotification($idTipoNotificacion, $asunto, $idUsuarioReceptor, $mensaje = null, $metaDataInfo = null)
    {
        $currentTime = \Carbon\Carbon::now();
        $notificacion = new Notificacion(); 
        $notificacion->idTipoNotificacion = $idTipoNotificacion;
        $notificacion->asunto = $asunto;
        $notificacion->mensaje = $mensaje;
        $notificacion->idUsuarioReceptor = $idUsuarioReceptor;
        $notificacion->idUsuarioRemitente  = auth()->user()->id;
        $notificacion->idEmpresa =  KeyUtil::idCompany();
        // $notificacion->metadataInfo = $metaDataInfo; // Column does not exist in DB
        $notificacion->hora = $currentTime->toTimeString();
        $notificacion->fecha = $currentTime->toDateString();
        $notificacion->estado_id = Status::ID_ACTIVE;
        $notificacion->save();

        return $notificacion;
    }

   
    private function getIdPersona($persona)
    {
        if (is_numeric($persona)) {
            return $persona;
        }
        $idPersona = $persona->idpersona;
        if (!$idPersona) {
            $idPersona = $persona->id;
        } 
        return $idPersona;
    } 
}