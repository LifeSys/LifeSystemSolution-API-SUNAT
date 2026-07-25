<?php

namespace App\Consultas\Services;

use App\Consultas\Contracts\IdentityProviderInterface;
use App\Consultas\DTOs\ConsultaResultado;
use App\Consultas\Exceptions\ConsultaException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Orquestador único del módulo de consultas de identidad.
 *
 * Responsabilidades:
 *  - Aplicar cache (compartido entre tenants: el nombre de un DNI es el
 *    mismo para todos) con lock para evitar llamadas duplicadas al
 *    proveedor cuando llegan varias solicitudes simultáneas por el mismo
 *    documento.
 *  - Delegar la consulta real al proveedor activo (inyectado por interfaz,
 *    resuelto según config('consultas.default_provider')).
 *  - Registrar auditoría (vía AuditLookupService), sin que un fallo de
 *    auditoría afecte nunca la respuesta al usuario.
 *
 * Ni este service ni el proveedor conocen el concepto de "Client" del
 * negocio (facturación) — esa decisión (guardar o no el resultado como
 * cliente del tenant) sigue siendo responsabilidad de quien llama
 * (ej. ClientResolverService / ConsultController), tal como ya funciona hoy.
 */
class ConsultaService
{
    public function __construct(
        private readonly IdentityProviderInterface $proveedor,
        private readonly AuditLookupService $auditoria,
    ) {
    }

    public function consultarDni(string $dni, ?int $tenantId = null, ?Request $request = null): ConsultaResultado
    {
        return $this->resolver(
            tipo: 'dni',
            numero: $dni,
            minutos: (int) config('consultas.cache.dni_minutes'),
            consulta: fn () => $this->proveedor->consultarDni($dni),
            tenantId: $tenantId,
            request: $request,
        );
    }

    public function consultarRuc(string $ruc, ?int $tenantId = null, ?Request $request = null): ConsultaResultado
    {
        return $this->resolver(
            tipo: 'ruc',
            numero: $ruc,
            minutos: (int) config('consultas.cache.ruc_minutes'),
            consulta: fn () => $this->proveedor->consultarRuc($ruc),
            tenantId: $tenantId,
            request: $request,
        );
    }

    public function consultarDniRuc(string $dni, ?int $tenantId = null, ?Request $request = null): ConsultaResultado
    {
        return $this->resolver(
            tipo: 'dni_ruc',
            numero: $dni,
            minutos: (int) config('consultas.cache.dni_ruc_minutes'),
            consulta: fn () => $this->proveedor->consultarDniRuc($dni),
            tenantId: $tenantId,
            request: $request,
        );
    }

    private function resolver(
        string $tipo,
        string $numero,
        int $minutos,
        \Closure $consulta,
        ?int $tenantId,
        ?Request $request,
    ): ConsultaResultado {
        $proveedorActivo = (string) config('consultas.default_provider');
        $cacheKey = "consultas:{$tipo}:{$numero}";
        $cacheHit = Cache::has($cacheKey);
        $inicio = microtime(true);

        try {
            // Lock: si llegan 20 solicitudes simultáneas por el mismo documento
            // antes de que la primera termine, solo una golpea al proveedor;
            // el resto espera y reutiliza el resultado recién cacheado.
            $resultado = Cache::lock("lock:{$cacheKey}", 10)->block(
                5,
                fn () => Cache::remember($cacheKey, now()->addMinutes($minutos), $consulta),
            );
        } catch (ConsultaException $excepcion) {
            $this->auditoria->registrar(
                tenantId: $tenantId,
                provider: $proveedorActivo,
                lookupType: $tipo,
                documentNumber: $numero,
                httpStatus: $excepcion->httpStatus,
                responseTimeMs: (int) round((microtime(true) - $inicio) * 1000),
                cacheHit: false,
                request: $request,
            );

            throw $excepcion;
        }

        $this->auditoria->registrar(
            tenantId: $tenantId,
            provider: $proveedorActivo,
            lookupType: $tipo,
            documentNumber: $numero,
            httpStatus: 200,
            responseTimeMs: (int) round((microtime(true) - $inicio) * 1000),
            cacheHit: $cacheHit,
            request: $request,
        );

        return $resultado->conFuente($cacheHit ? 'cache' : 'proveedor');
    }
}
