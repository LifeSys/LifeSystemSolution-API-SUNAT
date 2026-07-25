<?php

namespace App\Consultas\Exceptions;

use Throwable;

class ProveedorNoDisponibleException extends ConsultaException
{
    public static function conexionFallida(string $proveedor, ?Throwable $previous = null): self
    {
        return new self(
            mensaje: 'El servicio de consultas no está disponible en este momento. Intenta nuevamente en unos minutos.',
            httpStatus: 503,
            contexto: ['proveedor' => $proveedor],
            previous: $previous,
        );
    }

    public static function errorServidor(string $proveedor, int $statusCode): self
    {
        return new self(
            mensaje: 'El servicio de consultas no está disponible en este momento. Intenta nuevamente en unos minutos.',
            httpStatus: 503,
            contexto: ['proveedor' => $proveedor, 'status_proveedor' => $statusCode],
        );
    }
}
