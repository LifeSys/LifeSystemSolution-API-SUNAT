<?php

namespace App\Consultas\Exceptions;

class DatosInvalidosException extends ConsultaException
{
    public static function documentoNoEncontrado(string $tipo, string $numero): self
    {
        return new self(
            mensaje: 'No se encontró información para el documento consultado.',
            httpStatus: 404,
            contexto: ['tipo' => $tipo, 'numero' => $numero],
        );
    }

    public static function respuestaSinDatos(string $proveedor, string $numero): self
    {
        return new self(
            mensaje: 'No se encontró información para el documento consultado.',
            httpStatus: 404,
            contexto: ['proveedor' => $proveedor, 'numero' => $numero],
        );
    }
}
