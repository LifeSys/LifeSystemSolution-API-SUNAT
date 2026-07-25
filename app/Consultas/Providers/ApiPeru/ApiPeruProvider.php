<?php

namespace App\Consultas\Providers\ApiPeru;

use App\Consultas\Contracts\IdentityProviderInterface;
use App\Consultas\DTOs\ConsultaResultado;
use App\Consultas\Exceptions\DatosInvalidosException;

/**
 * Proveedor de identidad basado en ApiPeru.dev (https://docs.apiperu.dev).
 *
 * Traduce el JSON específico de ApiPeru.dev al DTO normalizado del módulo.
 * Si mañana cambias de proveedor, esta es la única clase que se reemplaza;
 * ConsultaService, el controller y los requests no cambian.
 */
class ApiPeruProvider implements IdentityProviderInterface
{
    public function __construct(
        private readonly ApiPeruClient $client,
    ) {
    }

    public function consultarDni(string $dni): ConsultaResultado
    {
        $body = $this->client->post('/dni', ['dni' => $dni]);

        $data = $body['data'] ?? null;

        if (empty($body['success']) || empty($data) || empty($data['nombre_completo'])) {
            throw DatosInvalidosException::respuestaSinDatos('apiperu', $dni);
        }

        return ConsultaResultado::paraDni(
            dni: $data['numero'] ?? $dni,
            nombreCompleto: $data['nombre_completo'],
            nombres: $data['nombres'] ?? null,
            apellidoPaterno: $data['apellido_paterno'] ?? null,
            apellidoMaterno: $data['apellido_materno'] ?? null,
        );
    }

    public function consultarRuc(string $ruc): ConsultaResultado
    {
        $body = $this->client->post('/ruc', ['ruc' => $ruc]);

        $data = $body['data'] ?? null;

        if (empty($body['success']) || empty($data) || empty($data['nombre_o_razon_social'])) {
            throw DatosInvalidosException::respuestaSinDatos('apiperu', $ruc);
        }

        return ConsultaResultado::paraRuc(
            ruc: $data['ruc'] ?? $ruc,
            razonSocial: $data['nombre_o_razon_social'],
            direccion: $data['direccion_completa'] ?? $data['direccion'] ?? null,
            estado: $data['estado'] ?? null,
            condicion: $data['condicion'] ?? null,
        );
    }

    public function consultarDniRuc(string $dni): ConsultaResultado
    {
        $body = $this->client->post('/dni-ruc', ['dni' => $dni]);

        // A diferencia de dni/ruc, aquí "success: true" con ruc vacío es una
        // respuesta VÁLIDA: significa "esta persona no tiene RUC registrado",
        // no un error del proveedor. Solo lanzamos excepción si la llamada
        // en sí falló (eso ya lo filtra ApiPeruClient antes de llegar aquí).
        $data = $body['data'] ?? [];

        return ConsultaResultado::paraDniRuc(
            dni: $dni,
            rucAsociado: $data['ruc'] ?? null,
        );
    }
}
