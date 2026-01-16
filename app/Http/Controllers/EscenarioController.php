<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use App\Models\ImagenEscenario;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class EscenarioController extends Controller
{
    public function index()
    {
        $idCompany = KeyUtil::idCompany(); 
    
        $escenarios = Escenario::with([
                'imagenes' => function($query) {
                    $query->where('tipo', 'imagen');
                },
                'videos' => function($query) {
                    $query->where('tipo', 'video');
                }
            ])
            ->where('idCompany', $idCompany)
            ->orderByDesc('created_at') 
            ->get();
            
        return response()->json($escenarios);
    }
    
    public function store(Request $request)
    {
        $data = $request->except(['imagenUrl', 'imagenes', 'videos']);
        $idCompany = KeyUtil::idCompany();
        $data['idCompany'] = $idCompany;
        
        $escenario = new Escenario($data);
        
        if ($request->hasFile('imagenUrl')) {
            $escenario->saveEscenarioImage($request);
        }
        
        $escenario->save();
        
        if ($request->hasFile('imagenes')) {
            $savedImages = $escenario->saveMultipleImages($request);
            foreach ($savedImages as $imageUrl) {
                ImagenEscenario::create([
                    'urlImage' => $imageUrl,
                    'tipo' => 'imagen',
                    'idEscenario' => $escenario->id
                ]);
            }
        }
        
        if ($request->hasFile('videos')) {
            $savedVideos = $escenario->saveMultipleVideos($request);
            foreach ($savedVideos as $videoUrl) {
                ImagenEscenario::create([
                    'urlVideo' => $videoUrl,
                    'tipo' => 'video',
                    'idEscenario' => $escenario->id
                ]);
            }
        }
    
        return response()->json($escenario->load('imagenes','videos'), 201);
    }

    public function update(Request $request, $id)
    {
        $escenario = Escenario::findOrFail($id);
        
        if ($request->hasFile('imagenUrl')) {
            $escenario->saveEscenarioImage($request);
        }
        
        $escenario->fill($request->except(['imagenUrl', 'imagenes', 'imagenesNuevas', 'imagenesAEliminar', 'videos', 'videosNuevos', 'videosAEliminar'])); 
        $escenario->save();
        
        if ($request->has('imagenesAEliminar')) {
            $imagenesAEliminar = json_decode($request->imagenesAEliminar, true);
            ImagenEscenario::where('idEscenario', $escenario->id)
                ->whereIn('id', $imagenesAEliminar)
                ->delete();
        }
        
        if ($request->hasFile('imagenesNuevas')) {
            foreach ($request->file('imagenesNuevas') as $image) {
                $tempRequest = new Request();
                $tempRequest->files->set('temp_image', $image);
                
                $imagePath = $escenario->storeFile(
                    $tempRequest,
                    'temp_image',
                    'escenario/multiple',
                    '/default/escenarios.jpg'
                );
                
                ImagenEscenario::create([
                    'urlImage' => $imagePath,
                    'tipo' => 'imagen',
                    'idEscenario' => $escenario->id
                ]);
            }
        }
        
        if ($request->has('videosAEliminar')) {
            $videosAEliminar = json_decode($request->videosAEliminar, true);
            ImagenEscenario::where('idEscenario', $escenario->id)
                ->whereIn('id', $videosAEliminar)
                ->delete();
        }
        
        if ($request->hasFile('videosNuevos')) {
            foreach ($request->file('videosNuevos') as $video) {
                $tempRequest = new Request();
                $tempRequest->files->set('temp_video', $video);
                
                $videoPath = $escenario->storeFile(
                    $tempRequest,
                    'temp_video',
                    'escenario/videos',
                    null
                );
                
                ImagenEscenario::create([
                    'urlVideo' => $videoPath,
                    'tipo' => 'video',
                    'idEscenario' => $escenario->id
                ]);
            }
        }
        
        return response()->json($escenario->load('imagenes'), 200);
    }


    public function show(Escenario $escenario)
    {
        return response()->json($escenario->load('imagenes'));
    }


    public function destroy($id)
    {
        $escenario = Escenario::findOrFail($id);
    
        $escenario->multimedia()->delete();
    
        $escenario->delete();
    
        return response()->json(null, 204);
    }
    
}