<?php

namespace App\Support;

/**
 * Clasificación de URL de Material de Apoyo (YouTube, PDF directo o enlace genérico).
 * No realiza peticiones HTTP: evita SSRF y no inventa el formato si no se puede confirmar.
 */
class MaterialCompetenciaEnlace
{
    public const FORMATO_YOUTUBE = 'youtube';

    public const FORMATO_PDF_EXTERNO = 'pdf_externo';

    public const FORMATO_ENLACE = 'enlace';

    public static function normalizarUrl(?string $raw): ?string
    {
        $u = trim((string) $raw);
        if ($u === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $u)) {
            $u = 'https://'.$u;
        }
        if (! filter_var($u, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($u, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $u;
    }

    public static function youtubeId(?string $raw): ?string
    {
        $u = self::normalizarUrl($raw);
        if (! $u) {
            return null;
        }
        $host = strtolower((string) parse_url($u, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $path = (string) parse_url($u, PHP_URL_PATH);
        parse_str((string) parse_url($u, PHP_URL_QUERY), $query);

        $hosts = ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'youtu.be'];
        $esYoutube = false;
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                $esYoutube = true;
                break;
            }
        }
        if (! $esYoutube) {
            return null;
        }

        if (! empty($query['v']) && is_string($query['v'])) {
            return self::idValido($query['v']);
        }

        if (preg_match('#/(embed|shorts|live|v)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
            return self::idValido($m[2]);
        }

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
            $id = explode('/', $id)[0] ?? '';

            return self::idValido($id);
        }

        return null;
    }

    public static function esPdfDirecto(?string $raw): bool
    {
        $u = self::normalizarUrl($raw);
        if (! $u) {
            return false;
        }
        $path = (string) parse_url($u, PHP_URL_PATH);

        return str_ends_with(strtolower($path), '.pdf');
    }

    /**
     * @return array{ok: bool, url?: string, formato?: string, mensaje?: string}
     */
    public static function clasificar(?string $raw): array
    {
        $url = self::normalizarUrl($raw);
        if (! $url) {
            return ['ok' => false, 'mensaje' => 'La URL no es válida. Use http o https.'];
        }
        if (self::youtubeId($url)) {
            return ['ok' => true, 'url' => $url, 'formato' => self::FORMATO_YOUTUBE];
        }
        if (self::esPdfDirecto($url)) {
            return ['ok' => true, 'url' => $url, 'formato' => self::FORMATO_PDF_EXTERNO];
        }

        return ['ok' => true, 'url' => $url, 'formato' => self::FORMATO_ENLACE];
    }

    public static function youtubeEmbedUrl(?string $raw): ?string
    {
        $id = self::youtubeId($raw);

        return $id ? 'https://www.youtube.com/embed/'.$id.'?rel=0' : null;
    }

    public static function youtubeWatchUrl(?string $raw): ?string
    {
        $id = self::youtubeId($raw);

        return $id ? 'https://www.youtube.com/watch?v='.$id : null;
    }

    public static function formatoDesdeRuta(?string $ruta): ?string
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return null;
        }
        if (self::youtubeId($ruta)) {
            return self::FORMATO_YOUTUBE;
        }
        $path = $ruta;
        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            $path = (string) parse_url($ruta, PHP_URL_PATH);
        }
        $path = strtolower(basename(strtok($path, '?')));
        if (preg_match('/\.([a-z0-9]+)$/', $path, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function idValido(string $id): ?string
    {
        $id = trim($id);

        return preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id) ? $id : null;
    }
}
