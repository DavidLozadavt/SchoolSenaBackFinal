<?php

namespace App\Models;

use App\Traits\SaveFile;
use App\Traits\UtilNotification;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Compromiso;
use App\Models\TipoNotificacion;

class AnotacionesDisciplinarias extends Model
{
    use HasFactory, SaveFile,  UtilNotification;
    protected $table = 'anotacionesDisciplinarias';
    protected $guarded = [];

    const PATH = "anotacionesDisciplinarias";
    const RUTA_FOTO_DEFAULT = "/default/imagenpordefecto.png";


    protected $appends = ['DocUrl'];


    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'idEstudiante', 'id');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idDocente');
    }

    public function compromisos()
    {
        return $this->hasMany(Compromiso::class, 'idAnotacionesDisciplinarias');
    }

    public function sanciones()
    {
        return $this->hasMany(Sancion::class, 'idAnotacionesDisciplinarias');
    }


    public function saveFileanotaciones($request)
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


    public function notificarAnotacionEstudiante($personasDestino)
    {
        $metadataInfo = [
            'idAnotacion' => $this->id,
            'gradoAnotacion' => $this->gradoAnotacion,
            'fecha' => $this->fecha,
            'observacion' => $this->observacion
        ];
        
        if (!is_array($personasDestino)) {
            $personasDestino = [$personasDestino];
        }

        foreach ($personasDestino as $personaId) {
            $this->sendNotification(
                TipoNotificacion::ID_ACTIVO,
                "ANOTACION DISCIPLINARIA",
                $personaId,
                "Has recibido una anotación disciplinaria.",
                json_encode($metadataInfo)
            );

            $persona = Person::find($personaId);
            if ($persona && $persona->email) {
                $mensaje = "Hola " . $persona->nombre1 . " " . $persona->apellido1 . ",\n\n";
                $mensaje .= "Te informamos que se ha creado una nueva anotación disciplinaria en tu contra.\n";
                $mensaje .= "Fecha: " . $this->fecha . "\n";
                $mensaje .= "Grado: " . $this->gradoAnotacion . "\n";
                $mensaje .= "Observación: " . $this->observacion . "\n\n";
                $mensaje .= "Por favor, ingresa a la plataforma para más detalles.\n";

                try {
                    // Obtener el centro de formación directamente desde la matrícula del estudiante
                    $this->loadMissing('matricula.ficha.sede.centroFormacion');
                    $centroFormacionName = $this->matricula->ficha->sede->centroFormacion->nombre ?? 'SENA';
                } catch (\Exception $e) {
                    $centroFormacionName = 'SENA';
                }

                \Illuminate\Support\Facades\Mail::to($persona->email)->send(
                    new \App\Mail\MailService("Notificación de Anotación Disciplinaria", $mensaje, $centroFormacionName)
                );
            }
        }
    }





}
