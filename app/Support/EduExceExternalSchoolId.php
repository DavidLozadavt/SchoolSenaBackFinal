<?php

namespace App\Support;

final class EduExceExternalSchoolId
{
    /** Evita colisión con ids de empresa regional (1, 2, …). */
    private const CENTRO_OFFSET = 900000;

    public static function forCentro(int $centroId): int
    {
        return self::CENTRO_OFFSET + $centroId;
    }

    public static function isCentroExternalId(int $externalSchoolId): bool
    {
        return $externalSchoolId >= self::CENTRO_OFFSET;
    }

    public static function centroIdFromExternal(int $externalSchoolId): ?int
    {
        if (!self::isCentroExternalId($externalSchoolId)) {
            return null;
        }

        return $externalSchoolId - self::CENTRO_OFFSET;
    }
}
