<?php

namespace App\Consultas\DTOs;

/**
 * Resultado normalizado de una consulta de identidad, sin importar qué
 * proveedor externo la resolvió. Los controllers (nuevos y legados) mapean
 * este DTO al shape de respuesta que necesiten mostrar hacia afuera.
 */
final class ConsultaResultado
{
    private function __construct(
        public readonly string $tipoDocumento,   // '1' = DNI, '6' = RUC
        public readonly string $numeroDocumento,
        public readonly ?string $nombreORazonSocial = null,
        public readonly ?string $nombres = null,
        public readonly ?string $apellidoPaterno = null,
        public readonly ?string $apellidoMaterno = null,
        public readonly ?string $direccion = null,
        public readonly ?string $estado = null,
        public readonly ?string $condicion = null,
        public readonly ?string $rucAsociado = null, // solo para dni-ruc
        public readonly ?bool $tieneRuc = null,      // solo para dni-ruc
        public readonly string $fuente = 'proveedor', // 'cache' | 'proveedor'
    ) {
    }

    public static function paraDni(
        string $dni,
        string $nombreCompleto,
        ?string $nombres = null,
        ?string $apellidoPaterno = null,
        ?string $apellidoMaterno = null,
        ?string $direccion = null,
    ): self {
        return new self(
            tipoDocumento: '1',
            numeroDocumento: $dni,
            nombreORazonSocial: $nombreCompleto,
            nombres: $nombres,
            apellidoPaterno: $apellidoPaterno,
            apellidoMaterno: $apellidoMaterno,
            direccion: $direccion,
        );
    }

    public static function paraRuc(
        string $ruc,
        string $razonSocial,
        ?string $direccion = null,
        ?string $estado = null,
        ?string $condicion = null,
    ): self {
        return new self(
            tipoDocumento: '6',
            numeroDocumento: $ruc,
            nombreORazonSocial: $razonSocial,
            direccion: $direccion,
            estado: $estado,
            condicion: $condicion,
        );
    }

    public static function paraDniRuc(string $dni, ?string $rucAsociado): self
    {
        return new self(
            tipoDocumento: '1',
            numeroDocumento: $dni,
            rucAsociado: $rucAsociado,
            tieneRuc: ! empty($rucAsociado),
        );
    }

    /**
     * Devuelve una copia marcada como resuelta desde cache (para logging/auditoría).
     */
    public function conFuente(string $fuente): self
    {
        $clon = clone $this;

        return new self(
            tipoDocumento: $clon->tipoDocumento,
            numeroDocumento: $clon->numeroDocumento,
            nombreORazonSocial: $clon->nombreORazonSocial,
            nombres: $clon->nombres,
            apellidoPaterno: $clon->apellidoPaterno,
            apellidoMaterno: $clon->apellidoMaterno,
            direccion: $clon->direccion,
            estado: $clon->estado,
            condicion: $clon->condicion,
            rucAsociado: $clon->rucAsociado,
            tieneRuc: $clon->tieneRuc,
            fuente: $fuente,
        );
    }

    /**
     * Formato estándar de la API (para los endpoints nuevos /consulta/*).
     */
    public function toArray(): array
    {
        return array_filter([
            'tipo_documento' => $this->tipoDocumento,
            'numero_documento' => $this->numeroDocumento,
            'nombre_o_razon_social' => $this->nombreORazonSocial,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'direccion' => $this->direccion,
            'estado' => $this->estado,
            'condicion' => $this->condicion,
            'ruc_asociado' => $this->rucAsociado,
            'tiene_ruc' => $this->tieneRuc,
            'fuente' => $this->fuente,
        ], fn ($valor) => $valor !== null);
    }
}
