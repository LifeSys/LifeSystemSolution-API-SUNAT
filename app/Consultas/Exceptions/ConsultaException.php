<?php

namespace App\Consultas\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Excepción base del módulo Consultas. Incluye un HTTP status sugerido y
 * un mensaje seguro para el usuario final (nunca se expone el error interno
 * del proveedor, credenciales, ni stack traces).
 */
class ConsultaException extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly int $httpStatus = 502,
        public readonly array $contexto = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($mensaje, 0, $previous);
    }
}
