<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SaveFile;
use App\Traits\UtilNotification;
use App\Models\TipoNotificacion;
use App\Models\Person;
use App\Models\Contract;

class Sancion extends Model
{
    use HasFactory, SaveFile, UtilNotification;

    protected $table = 'sancion';
    protected $guarded = [];
    const PATH = "sanciones";
    protected $appends = ['DocUrl'];
    const RUTA_FOTO_DEFAULT = "/default/imagenpordefecto.png";


    public function anotacion()
    {
        return $this->belongsTo(AnotacionesDisciplinarias::class, 'idAnotacionesDisciplinarias');
    }
    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idDocente');
    }

    public function saveFileSanctions($request)
    {
        $default = null;
        if (isset($this->attributes['urlDocumento'])) {
            $default = $this->attributes['urlDocumento'];
        }
        $this->attributes['urlDocumento'] = $this->storeFile(
            $request,
            'urlDocumentoFile',
            self::PATH,
            $default
        );
        return $this->attributes['urlDocumento'];
    }
       public function getDocUrlAttribute()
    {
        if (
            isset($this->attributes['urlDocumento']) &&
            isset($this->attributes['urlDocumento'][0])
        ) {
            return url($this->attributes['urlDocumento']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }

    public function notificarsancionEstudiante($personasDestino)
    {
        $metadataInfo = [
            'idSancion' => $this->id,
            'idAnotacionesDisciplinarias' => $this->idAnotacionesDisciplinarias,
            'fechaInicial' => $this->fechaInicial,
            'fechaFinal' => $this->fechaFinal,
            'gradoSancion' => $this->gradoSancion,
            'observacion' => $this->observacion
        ];
        
        if (!is_array($personasDestino)) {
            $personasDestino = [$personasDestino];
        }

        foreach ($personasDestino as $personaId) {
            $this->sendNotification(
                TipoNotificacion::ID_ACTIVO,
                "NUEVA SANCIÓN",
                $personaId,
                "Se ha registrado una nueva sanción disciplinaria.",
                json_encode($metadataInfo)
            );

            $persona = Person::find($personaId);
            if ($persona && $persona->email) {
                $mensaje = "Hola " . $persona->nombre1 . " " . $persona->apellido1 . ",\n\n";
                $mensaje .= "Te informamos que se ha registrado una sanción asociada a una anotación disciplinaria.\n";
                $mensaje .= "Período: " . $this->fechaInicial . " a " . $this->fechaFinal . " \n";
                $mensaje .= "Grado: " . $this->gradoSancion . "\n";
                $mensaje .= "Observación: " . $this->observacion . "\n\n";
                $mensaje .= "Por favor, ingresa a la plataforma para conocer los detalles completos.\n";

                try {
                    // Obtener el centro de formación directamente desde la matrícula del estudiante
                    $this->loadMissing('anotacion.matricula.ficha.sede.centroFormacion');
                    $centroFormacionName = $this->anotacion->matricula->ficha->sede->centroFormacion->nombre ?? 'SENA';
                } catch (\Exception $e) {
                    $centroFormacionName = 'SENA';
                }

                \Illuminate\Support\Facades\Mail::to($persona->email)->send(
                    new \App\Mail\MailService("Notificación de Sanción Disciplinaria", $mensaje, $centroFormacionName)
                );
            }
        }
    }
}