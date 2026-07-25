<?php

namespace App\Consultas\Providers\ApiPeru;

use App\Consultas\Exceptions\CredencialesInvalidasException;
use App\Consultas\Exceptions\ProveedorNoDisponibleException;
use App\Consultas\Exceptions\ProveedorTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Encapsula toda la comunicación HTTP con ApiPeru.dev.
 *
 * Responsabilidad única: hablar HTTP (headers, base URL, timeout) y traducir
 * errores de transporte a excepciones tipadas del módulo. No sabe nada de
 * DNI, RUC, ni de cómo se usan los datos — eso vive en ApiPeruProvider.
 */
class ApiPeruClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout,
    ) {
    }

    /**
     * Ejecuta un POST contra un endpoint de ApiPeru.dev y devuelve el body
     * decodificado como array. Lanza excepciones tipadas ante cualquier fallo.
     *
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $payload): array
    {
        $inicio = microtime(true);

        try {
            $respuesta = Http::baseUrl($this->baseUrl)
                ->withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($endpoint, $payload);
        } catch (ConnectionException $excepcion) {
            if (str_contains(strtolower($excepcion->getMessage()), 'timed out')) {
                throw ProveedorTimeoutException::agotado('apiperu', $this->timeout, $excepcion);
            }

            throw ProveedorNoDisponibleException::conexionFallida('apiperu', $excepcion);
        }

        $duracionMs = (int) round((microtime(true) - $inicio) * 1000);

        if ($respuesta->status() === 401 || $respuesta->status() === 403) {
            \Log::error('ApiPeru: credenciales rechazadas', [
                'endpoint' => $endpoint,
                'status' => $respuesta->status(),
                'duracion_ms' => $duracionMs,
            ]);

            throw CredencialesInvalidasException::tokenRechazado('apiperu');
        }

        if ($respuesta->serverError()) {
            \Log::error('ApiPeru: error de servidor', [
                'endpoint' => $endpoint,
                'status' => $respuesta->status(),
                'duracion_ms' => $duracionMs,
            ]);

            throw ProveedorNoDisponibleException::errorServidor('apiperu', $respuesta->status());
        }

        if ($respuesta->clientError() && $respuesta->status() !== 404 && $respuesta->status() !== 422) {
            \Log::error('ApiPeru: error de cliente inesperado', [
                'endpoint' => $endpoint,
                'status' => $respuesta->status(),
                'duracion_ms' => $duracionMs,
            ]);

            throw ProveedorNoDisponibleException::errorServidor('apiperu', $respuesta->status());
        }

        \Log::info('ApiPeru: llamada completada', [
            'endpoint' => $endpoint,
            'status' => $respuesta->status(),
            'duracion_ms' => $duracionMs,
        ]);

        return $respuesta->json() ?? [];
    }
}
