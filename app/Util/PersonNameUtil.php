<?php

namespace App\Util;

/**
 * Normalización y validación de nombres/apellidos (compuestos con espacios).
 * Regla alineada con schoolSenaFrontFinal/src/utils/personNameValidation.ts
 */
class PersonNameUtil
{
    public const PATTERN = '/^[A-Za-zÁÉÍÓÚáéíóúÑñüÜ]+(?: [A-Za-zÁÉÍÓÚáéíóúÑñüÜ]+){0,2}$/u';

    public static function normalize(?string $value): string
    {
        $trimmed = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? '';
    }

    public static function countAlphabeticChars(string $value): int
    {
        preg_match_all('/[A-Za-zÁÉÍÓÚáéíóúÑñüÜ]/u', $value, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * @return string|null Mensaje de error o null si es válido
     */
    public static function validate(?string $value, bool $required, string $label): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return $required ? "{$label} es obligatorio." : null;
        }

        if (!preg_match(self::PATTERN, $normalized)) {
            return "{$label} solo puede contener letras y hasta tres palabras separadas por un espacio.";
        }

        if (self::countAlphabeticChars($normalized) < 2) {
            return "{$label} debe tener al menos 2 letras.";
        }

        return null;
    }
}
