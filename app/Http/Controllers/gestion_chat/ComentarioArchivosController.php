<?php

namespace App\Http\Controllers\gestion_chat;

use App\Http\Controllers\Controller;
use App\Models\ComentarioArchivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComentarioArchivosController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return JsonResponse
	 */
	public static function index(Request $request = null): JsonResponse
	{
		$archivos = ComentarioArchivos::all();
		return response()->json($archivos, 200);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  ComentarioArchivos  $comentarioArchivos
	 * @return JsonResponse
	 */
	public static function show(int $id): JsonResponse
	{
		$comentarioArchivo = ComentarioArchivos::findOrFail($id);
		return response()->json($comentarioArchivo);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  Request  $request
	 * @return JsonResponse
	 */
	public static function store(Request $request): JsonResponse
	{
		$data = $request->all();
		if ($request->hasFile('archivo')) {
			$imagen = $request->file('archivo');
			$nombreArchivo = uniqid('document') . '_' . $imagen->getClientOriginalName();
			$rutaAlmacenamiento = $imagen->storeAs('public/documentos/archivoscomentario', $nombreArchivo);
			$rutaImagenGuardada = Storage::url($rutaAlmacenamiento);
			$data['archivo'] = $rutaImagenGuardada;
		}
		$archivoComentario = ComentarioArchivos::create($data);
		return response()->json($archivoComentario, 201);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  Request  $request
	 * @param  ComentarioArchivos  $comentarioArchivos
	 * @return JsonResponse
	 */
	public static function update(Request $request, int $id): JsonResponse
	{
		$data = $request->all();
		$comentarioArchivo = ComentarioArchivos::findOrFail($id);
		self::deleteFile($comentarioArchivo);
		if ($request->hasFile('archivo')) {
			$imagen = $request->file('archivo');
			$nombreArchivo = uniqid('document') . '_' . $imagen->getClientOriginalName();
			$rutaAlmacenamiento = $imagen->storeAs('public/documentos/archivoscomentario', $nombreArchivo);
			$rutaImagenGuardada = Storage::url($rutaAlmacenamiento);
			$data['archivo'] = $rutaImagenGuardada;
		}
		$comentarioArchivo->update($data);
		return response()->json($comentarioArchivo, 200);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  ComentarioArchivos  $comentarioArchivos
	 * @return JsonResponse
	 */
	public static function destroy(int $id): JsonResponse
	{
		$comentarioArchivo = ComentarioArchivos::findOrFail($id);
		$comentarioArchivo->delete();
		return response()->json(null, 204);
	}

	/**
	 * Delete file of comentarioArchivo
	 *
	 * @param object $pago
	 * @return void
	 */
	private static function deleteFile($archivo): void
	{
		$rutaImagen = $archivo->archivo;

		$fileUrl = str_replace('storage/', 'public/', $rutaImagen); // Reemplazar ruta de storage por public

		if ($archivo->archivo != '') {
			Storage::delete($fileUrl);
		}
	}

	/**
	 * Create files of comments
	 *
	 * @param Request $request
	 * @param int $idComment
	 * @return JsonResponse
	 */
	public static function createFiles(Request $request, int $idComment): JsonResponse
	{
		// Validar que haya archivos en la solicitud
		if (empty($request->archivos)) {
			return response()->json(['message' => 'No se encontraron archivos en la solicitud'], 400);
		}

		$urlFiles = [];
		foreach ($request->archivos as $archivo) {
			$nombreArchivo = uniqid('document') . '_' . $archivo->getClientOriginalName();
			$rutaAlmacenamiento = $archivo->storeAs('public/documentos/archivoscomentario', $nombreArchivo);
			$rutaImagenGuardada = Storage::url($rutaAlmacenamiento);

			// Crear un registro en la base de datos para cada archivo
			ComentarioArchivos::create([
				'idComentario' => $idComment,
				'archivo' => $rutaImagenGuardada
			]);

			$urlFiles[] = $rutaImagenGuardada;
		}

		return response()->json($urlFiles, 201);
	}

	/**
	 * Download file
	 *
	 * @param integer $idComentarioArchivo
	 * @return void
	 */
	public function downloadFile(int $idComentarioArchivo)
	{
		$file = ComentarioArchivos::findOrFail($idComentarioArchivo);
		// Lógica para buscar y descargar el archivo
		$rutaArchivo = $file->archivo;

		// Eliminar el prefijo de la URL si existe
		$rutaArchivoRelativa = parse_url($rutaArchivo, PHP_URL_PATH);

		// Reemplazar /storage/ con app/public/ en la ruta relativa
		$rutaArchivoSinStorage = str_replace('/storage/', 'app/public/', $rutaArchivoRelativa);

		// Devuelve el archivo como una respuesta HTTP con el tipo de contenido adecuado
		return response()->file(storage_path($rutaArchivoSinStorage), ['Content-Type' => 'text/plain']);
	}
}
