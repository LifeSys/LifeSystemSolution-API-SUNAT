<?php

namespace App\Consultas\Exceptions;

class CredencialesInvalidasException extends ConsultaException
{
    public static function tokenRechazado(string $proveedor): self
    {
        return new self(
            mensaje: 'El servicio de consultas no está disponible en este momento. Contacta al administrador.',
            httpStatus: 503,
            // Deliberadamente no se incluye el token ni fragmentos de él en el contexto.
            contexto: ['proveedor' => $proveedor],
        );
    }
}
