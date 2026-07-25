<?php

namespace App\Consultas\Contracts;

use App\Consultas\DTOs\ConsultaResultado;

/**
 * Contrato que debe implementar cualquier proveedor de consultas de
 * identidad (DNI/RUC). Permite cambiar de proveedor (ApiPeru, apis.net.pe,
 * decolecta, etc.) sin modificar el service, el controller ni los requests.
 */
interface IdentityProviderInterface
{
    /**
     * Consulta un DNI en RENIEC.
     */
    public function consultarDni(string $dni): ConsultaResultado;

    /**
     * Consulta un RUC en SUNAT.
     */
    public function consultarRuc(string $ruc): ConsultaResultado;

    /**
     * Consulta el RUC asociado a una persona natural a partir de su DNI.
     */
    public function consultarDniRuc(string $dni): ConsultaResultado;
}
