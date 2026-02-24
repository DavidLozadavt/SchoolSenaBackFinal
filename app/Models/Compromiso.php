<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SaveFile;
use App\Traits\UtilNotification;
use App\Models\TipoNotificacion;
use App\Models\Person;
use App\Models\Contract;

class Compromiso extends Model
{
    use HasFactory, SaveFile, UtilNotification;

    protected $table = 'compromisos';
    protected $guarded = [];
    const PATH = "compromisos";
    const RUTA_FOTO_DEFAULT = "/default/imagenpordefecto.png";

    protected $appends = ['DocUrl'];


    public function anotacion()
    {
        return $this->belongsTo(AnotacionesDisciplinarias::class, 'idAnotacionesDisciplinarias');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idDocente');
    }

     public function saveFileanotaciones($request)
    {
        $default = '/default/user.svg';
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


    public function notificarCompromisoEstudiante($personasDestino)
    {
        $metadataInfo = [
            'idCompromiso' => $this->id,
            'idAnotacionesDisciplinarias' => $this->idAnotacionesDisciplinarias,
            'fecha' => $this->fecha,
            'observacion' => $this->observacion
        ];
        
        if (!is_array($personasDestino)) {
            $personasDestino = [$personasDestino];
        }

        foreach ($personasDestino as $personaId) {
            $this->sendNotification(
                TipoNotificacion::ID_ACTIVO,
                "NUEVO COMPROMISO",
                $personaId,
                "Se ha registrado un nuevo compromiso disciplinario.",
                json_encode($metadataInfo)
            );

            $persona = Person::find($personaId);
            if ($persona && $persona->email) {
                $mensaje = "Hola " . $persona->nombre1 . " " . $persona->apellido1 . ",\n\n";
                $mensaje .= "Te informamos que se ha registrado un nuevo compromiso asociado a una anotación disciplinaria.\n";
                $mensaje .= "Fecha: " . $this->fecha . "\n";
                $mensaje .= "Observación: " . $this->observacion . "\n\n";
                $mensaje .= "Por favor, ingresa a la plataforma para más detalles y no olvides cumplirlo.\n";

                try {
                    // Obtener el centro de formación directamente desde la matrícula del estudiante
                    $this->loadMissing('anotacion.matricula.ficha.sede.centroFormacion');
                    $centroFormacionName = $this->anotacion->matricula->ficha->sede->centroFormacion->nombre ?? 'SENA';
                } catch (\Exception $e) {
                    $centroFormacionName = 'SENA';
                }

                \Illuminate\Support\Facades\Mail::to($persona->email)->send(
                    new \App\Mail\MailService("Notificación de Compromiso Disciplinario", $mensaje, $centroFormacionName)
                );
            }
        }
    }
}
