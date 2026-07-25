<?php

namespace App\Services;

use App\Consultas\Exceptions\ConsultaException;
use App\Consultas\Services\ConsultaService;

/**
 * @deprecated Adaptador de compatibilidad. La lógica real vive ahora en
 * App\Consultas (ConsultaService + ApiPeruProvider). Esta clase existe
 * únicamente para que cualquier código que aún inyecte
 * App\Services\DocumentLookupService siga funcionando sin cambios mientras
 * se termina de migrar (ver ConsultController::lookupDocument y
 * ClienteController::lookupRuc). Se registra cada invocación para poder
 * confirmar, por logs, cuándo ya no queda tráfico y es seguro eliminarla.
 *
 * NO usar esta clase en código nuevo: inyectar directamente
 * App\Consultas\Services\ConsultaService.
 */
class DocumentLookupService
{
    public function __construct(
        private readonly ConsultaService $consultas,
    ) {
    }

    /**
     * Mantiene el shape de respuesta legado exacto que ya consumía
     * ConsultController::lookupDocument, para no romper al POS.
     */
    public function lookup(string $tipo, string $numero): ?array
    {
        \Log::info('DocumentLookupService (legacy) invocado', ['tipo' => $tipo, 'numero' => $numero]);

        try {
            if ($tipo === '6') {
                $resultado = $this->consultas->consultarRuc($numero);

                return [
                    'tipo_doc' => '6',
                    'num_doc' => $resultado->numeroDocumento,
                    'razon_social' => $resultado->nombreORazonSocial,
                    'direccion' => $resultado->direccion ?? '',
                    'estado' => $resultado->estado ?? '',
                    'condicion' => $resultado->condicion ?? '',
                    'source' => 'sunat',
                ];
            }

            $resultado = $this->consultas->consultarDni($numero);

            return [
                'tipo_doc' => '1',
                'num_doc' => $resultado->numeroDocumento,
                'razon_social' => $resultado->nombreORazonSocial,
                'nombres' => $resultado->nombres ?? '',
                'apellido_paterno' => $resultado->apellidoPaterno ?? '',
                'apellido_materno' => $resultado->apellidoMaterno ?? '',
                'direccion' => $resultado->direccion ?? '',
                'source' => 'reniec',
            ];
        } catch (ConsultaException $excepcion) {
            // Preserva el contrato original: null en vez de excepción.
            \Log::error("Lookup legado falló para {$tipo}:{$numero}: " . $excepcion->getMessage());

            return null;
        }
    }
}
