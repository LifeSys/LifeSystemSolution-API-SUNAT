<?php

namespace App\Consultas\Exceptions;

use Throwable;

class ProveedorTimeoutException extends ConsultaException
{
    public static function agotado(string $proveedor, int $timeoutSegundos, ?Throwable $previous = null): self
    {
        return new self(
            mensaje: 'La consulta tardó demasiado en responder. Intenta nuevamente.',
            httpStatus: 504,
            contexto: ['proveedor' => $proveedor, 'timeout_segundos' => $timeoutSegundos],
            previous: $previous,
        );
    }
}
