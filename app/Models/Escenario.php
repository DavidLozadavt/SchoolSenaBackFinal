<?php

namespace App\Models;

use App\Traits\SaveFile;
use App\Traits\UtilNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Escenario extends Model
{
    use HasFactory, UtilNotification, SaveFile;

    public static $snakeAttributes = false;
    protected $table = 'escenario';
    protected $guarded = [];
    const PATH = "escenario";
    const RUTA_FOTO_DEFAULT = "/default/escenarios.jpg";
    const MULTIPLE_IMAGES_PATH = "escenario/multiple";
    const MULTIPLE_IMAGES_DEFAULT = "/default/escenarios.jpg";
    const MULTIPLE_VIDEOS_PATH = 'escenario/videos';
    protected $appends = ['imagenUrl'];

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }
    public function saveEscenarioImage(Request $request)
    {
        $default = $this->attributes['imagenUrl'] ?? self::RUTA_FOTO_DEFAULT;
        $this->attributes['imagenUrl'] = $this->storeFile(
            $request,
            'imagenUrl',
            self::PATH,
            $default
        );
        return $this->attributes['imagenUrl'];
    }

    public function getImagenUrlAttribute()
    {
        if (
            isset($this->attributes['imagenUrl']) &&
            !empty($this->attributes['imagenUrl'])
        ) {
            return url($this->attributes['imagenUrl']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }



    public function saveMultipleImages(Request $request)
    {
        $savedImages = [];

        if ($request->hasFile('imagenes')) {
            foreach ($request->file('imagenes') as $image) {
                $tempRequest = new Request();
                $tempRequest->files->set('temp_image', $image);

                $imagePath = $this->storeFile(
                    $tempRequest,
                    'temp_image',
                    self::MULTIPLE_IMAGES_PATH,
                    self::MULTIPLE_IMAGES_DEFAULT
                );

                $savedImages[] = $imagePath;
            }
        }

        return $savedImages;
    }
    public function saveMultipleVideos(Request $request)
    {
        $savedVideos = [];

        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {
                $tempRequest = new Request();
                $tempRequest->files->set('temp_video', $video);

                $videoPath = $this->storeFile(
                    $tempRequest,
                    'temp_video',
                    self::MULTIPLE_VIDEOS_PATH,
                    null
                );

                $savedVideos[] = $videoPath;
            }
        }

        return $savedVideos;
    }
    public function imagenes()
    {
        return $this->hasMany(ImagenEscenario::class, 'idEscenario')
            ->where('tipo', 'imagen')
            ->select([
                'id',
                'urlImage',
                'idEscenario',
                'tipo',
            ]);
    }

    public function videos()
    {
        return $this->hasMany(ImagenEscenario::class, 'idEscenario')
            ->where('tipo', 'video')
            ->select([
                'id',
                'urlVideo',
                'idEscenario',
                'tipo',
            ]);
    }

    public function multimedia()
    {
        return $this->hasMany(ImagenEscenario::class, 'idEscenario');
    }


    public function servicios()
    {
        return $this->belongsToMany(
            Servicio::class,
            'asignacionEscenarioServicio',
            'idEscenario',
            'idServicio'
        );
    }

    public function asignacionesServicio()
    {
        return $this->hasMany(AsignacionEscenarioServicio::class, 'idEscenario');
    }


}
