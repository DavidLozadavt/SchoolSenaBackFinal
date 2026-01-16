<?php

namespace App\Traits;

use Illuminate\Support\Facades\Session;

trait SaveFile
{

    public function storeFile(
        $request,
        $fileName,
        $path,
        $defaultPath = "default.jpg"
    ) {
        $ruta = $defaultPath;
        if ($request->hasFile($fileName)) {
            $ruta = "/storage/" . $request->file($fileName)->store($path, ['disk' => 'public']);
        }
        return $ruta;
    }
}
