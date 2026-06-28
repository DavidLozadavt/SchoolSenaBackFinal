<?php

namespace App\Support;

final class EduExceErrorMessage
{
    public static function forUser(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'Ocurrió un error al procesar la solicitud.';
        }

        $msg = trim($raw);

        if (stripos($msg, 'value too long for type character varying') !== false) {
            return 'Algunos datos del aprendiz exceden el tamaño permitido en EduExce. Se ajustó el envío; intente sincronizar de nuevo.';
        }

        if (stripos($msg, 'SQLSTATE') !== false || stripos($msg, 'insert into') !== false) {
            return 'No se pudo registrar un aprendiz en EduExce. Intente de nuevo o contacte a soporte.';
        }

        if (stripos($msg, 'documento ya está registrado') !== false) {
            return 'El documento ya existe en otra institución EduExce.';
        }

        if (stripos($msg, 'Institución no provisionada') !== false) {
            return 'La institución aún no está lista en EduExce. Active la licencia o contacte a Virtual Technology.';
        }

        if (
            stripos($msg, 'ENOTFOUND') !== false
            || stripos($msg, 'tenant/user postgres') !== false
            || stripos($msg, 'getaddrinfo ENOTFOUND') !== false
        ) {
            return 'No hay conexión con la base de datos EduExce (Supabase). Reactive el proyecto en supabase.com o contacte a soporte VT.';
        }

        if (strlen($msg) > 160) {
            return 'Error al sincronizar con EduExce. Intente de nuevo.';
        }

        return $msg;
    }

    public static function truncar(?string $valor, int $max): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        return mb_strlen($valor) > $max ? mb_substr($valor, 0, $max) : $valor;
    }
}
