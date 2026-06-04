<?php

namespace App\Enums;

enum EstadoSeguimientoInscripcion: string
{
    case BORRADOR = 'BORRADOR';
    case SOLICITUD_RECIBIDA = 'SOLICITUD_RECIBIDA';
    case EN_REVISION = 'EN_REVISION';
    case ACEPTADA = 'ACEPTADA';
    case FACTURA_GENERADA = 'FACTURA_GENERADA';
    case PENDIENTE_PAGO = 'PENDIENTE_PAGO';
    case PAGO_EN_REVISION = 'PAGO_EN_REVISION';
    case PAGO_APROBADO = 'PAGO_APROBADO';
    case INSCRIPCION_APROBADA = 'INSCRIPCION_APROBADA';
    case CORRECCION_SOLICITADA = 'CORRECCION_SOLICITADA';
    case RECHAZADA = 'RECHAZADA';

    public function etiquetaPortal(): string
    {
        return match ($this) {
            self::BORRADOR => 'En preparación',
            self::SOLICITUD_RECIBIDA => 'Solicitud recibida',
            self::EN_REVISION => 'En revisión',
            self::ACEPTADA => 'Inscripción aceptada',
            self::FACTURA_GENERADA => 'Factura generada',
            self::PENDIENTE_PAGO => 'Pendiente de pago',
            self::PAGO_EN_REVISION => 'Pago en revisión',
            self::PAGO_APROBADO => 'Pago aprobado',
            self::INSCRIPCION_APROBADA => 'Inscripción aprobada',
            self::CORRECCION_SOLICITADA => 'Corrección solicitada',
            self::RECHAZADA => 'Rechazada',
        };
    }

    public function mensajePortal(): string
    {
        return match ($this) {
            self::SOLICITUD_RECIBIDA, self::BORRADOR, self::CORRECCION_SOLICITADA => 'Su solicitud fue recibida y está siendo revisada por la institución.',
            self::EN_REVISION => 'Su solicitud está siendo revisada por la institución.',
            self::ACEPTADA, self::FACTURA_GENERADA, self::PENDIENTE_PAGO => 'Su inscripción fue aceptada. Tiene un pago pendiente.',
            self::PAGO_EN_REVISION => 'Su pago está siendo validado por el área administrativa.',
            self::PAGO_APROBADO => 'Su pago fue aprobado correctamente.',
            self::INSCRIPCION_APROBADA => 'Su inscripción fue aprobada exitosamente.',
            self::RECHAZADA => 'Su solicitud fue rechazada.',
        };
    }
}
