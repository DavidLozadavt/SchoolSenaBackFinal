<?php

namespace App\Util;

use App\Models\Estado;
use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\JsonResponse;

class QueryUtil
{
    public static function whereCompany(Builder $query): Builder
    {
        $idCompany = KeyUtil::idCompany();
        return $query->where(function ($query) use ($idCompany) {
            $query->where('idCompany', $idCompany);
        });
    }

    public static function whereUser(Builder $query): Builder
    {
        $idUser = auth()->id();
        $query = $query->whereHas('user', function ($query) use ($idUser) {
            $query->where('id', $idUser);
        });
        return $query;
    }

    public static function whereActive(Builder $query): Builder
    {
        $now = Carbon::now();
        return $query
            ->where(function ($query) use ($now) {
                   $query->whereIn('state_id', [1,18])

                    ->whereDate('fechaInicio', '<=', $now)
                    ->whereDate('fechaFin', '>=', $now);
            });

    }

    public static function createWithCompany(array $request): array
    {
        $request['company_id'] = KeyUtil::idCompany();
        return $request;
    }

    public static function whereLike(Builder $query, ?array $data, string $dataKey): Builder
    {
        if ($data !== null && isset($data[$dataKey])) {
            return $query->where($dataKey, 'LIKE', '%' . $data[$dataKey] . '%');
        }
        return $query;
    }
    public static function where(Builder $query, ?array $data, string $dataKey): Builder
    {
        if ($data !== null && isset($data[$dataKey])) {
            return $query->where($dataKey, $data[$dataKey]);
        }
        return $query;
    }

    public static function whereDoesntHave(Builder $query, ?array $data, string $dataKey, string $relationName): Builder
    {

        if ($data !== null && isset($data[$dataKey])) {
            $query->whereDoesntHave($relationName, function ($query) use ($data, $dataKey) {
                $query->where($dataKey, $data[$dataKey]);
            });
        }
        // var_dump($query->count());
        return $query;
    }

    public static function handleQueryException(QueryException $exception)
    {
        $errorCode = $exception->errorInfo[1];
        $errorMessage = $exception->getMessage();
        preg_match("/REFERENCES `(.*?)` \(`.*?`\)/", $errorMessage, $matches);
        $tableName = isset($matches[1]) ? $matches[1] : '';

        // Obtener información específica del error
        $errorDetails = self::getErrorDetails($errorCode, $errorMessage);

        switch ($errorCode) {
            case 1048:
                throw new Exception("Error en la tabla '{$tableName}': Campos obligatorios no pueden estar vacíos. Campos: {$errorDetails['fields']}", 500);
                break;
            case 1054:
                throw new Exception("Error en la tabla '{$tableName}': Columna '{$errorDetails['field']}' no encontrada.", 500);
                break;
            case 1062:
                throw new Exception("Error en la tabla '{$tableName}': Duplicado encontrado en la clave '{$errorDetails['key']}'. Valor duplicado: '{$errorDetails['value']}'.", 500);
                break;
            case 1451: // Nuevo caso para el error de clave externa

                throw new Exception("No se puede eliminar '{$tableName}', porque tiene contenido asignado.", 500);
                break;
            default:
                throw new Exception("Ocurrió un error en la tabla '{$tableName}'. Detalles: $errorMessage", 500);
                break;
        }
    }


    private static function getErrorDetails($errorCode, $errorMessage)
    {
        $errorDetails = [
            'table' => '',
            'field' => '',
            'key' => '',
            'value' => '',
            'fields' => '',
        ];

        switch ($errorCode) {
            case 1048:
                // Obtener el nombre de la tabla
                if (preg_match("/Table '(.+?)' doesn't exist/", $errorMessage, $matches)) {
                    $tableName = $matches[1];
                }

                // Obtener los nombres de los campos afectados
                if (preg_match("/Column (.+?) cannot be null/", $errorMessage, $matches)) {
                    $errorDetails['fields'] = $matches[1];
                }
                break;
            case 1054:
                // Obtener el nombre de la tabla
                if (preg_match("/Table '(.+?)' doesn't exist/", $errorMessage, $matches)) {
                    $tableName = $matches[1];
                }

                // Obtener el nombre de la columna no encontrada
                if (preg_match("/Unknown column '(.+?)' in/", $errorMessage, $matches)) {
                    $errorDetails['field'] = $matches[1];
                }
                break;
            case 1062:
                // Obtener el nombre de la tabla
                if (preg_match("/Duplicate entry for key '(.+?)'/", $errorMessage, $matches)) {
                    $errorDetails['key'] = $matches[1];
                }

                // Obtener el valor duplicado
                if (preg_match("/Duplicate entry '(.+?)' for key/", $errorMessage, $matches)) {
                    $errorDetails['value'] = $matches[1];
                }
                break;
            default:
                // Obtener el nombre de la tabla si está disponible
                if (preg_match("/Table '(.+?)'/", $errorMessage, $matches)) {
                    $tableName = $matches[1];
                }
                break;
        }

        return $errorDetails;
    }


    /**
     * Return exception in function
     *
     * @param \Exception $e
     * @return JsonResponse
     */
    public static function showExceptions(\Exception $e): JsonResponse
    {
        if ($e instanceof ModelNotFoundException) {
            return response()->json(['error' => 'Registro no encontrado ' . $e], 404);
        } elseif ($e instanceof QueryException) {
            return response()->json(['error' => 'Error de base de datos ' . $e], 500);
        } elseif ($e instanceof ValidationException) {
            return response()->json(['error' => 'Error de validación', 'detalle' => $e->errors()], 422);
        } elseif ($e instanceof HttpException) {
            return response()->json(['error' => 'Error HTTP', 'codigo' => $e->getStatusCode()], $e->getStatusCode());
        } else {
            return response()->json(['error' => 'Error interno del servidor ' . $e], 500);
        }
    }
}
