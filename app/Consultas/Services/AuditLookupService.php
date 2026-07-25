<?php

namespace App\Consultas\Services;

use App\Models\LookupQuery;
use Illuminate\Http\Request;
use Throwable;

/**
 * Registra cada consulta de identidad/datos públicos para auditoría y
 * control de consumo por tenant. Aislado en su propio servicio: si mañana
 * se quiere enviar esta métrica a Prometheus, OpenTelemetry o Grafana además
 * de (o en vez de) guardarla en PostgreSQL, solo se modifica esta clase —
 * ConsultaService no se entera del destino de la auditoría.
 */
class AuditLookupService
{
    /**
     * Registra una consulta. Nunca lanza excepciones: si falla el registro,
     * se loguea el error y se continúa, para que un problema de auditoría
     * jamás tumbe la respuesta real al usuario.
     */
    public function registrar(
        ?int $tenantId,
        string $provider,
        string $lookupType,
        string $documentNumber,
        ?int $httpStatus,
        ?int $responseTimeMs,
        bool $cacheHit,
        ?Request $request = null,
    ): void {
        try {
            LookupQuery::create([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'lookup_type' => $lookupType,
                'document_number' => $documentNumber,
                'http_status' => $httpStatus,
                'response_time_ms' => $responseTimeMs,
                'cache_hit' => $cacheHit,
                'requested_by_user_id' => $request?->user()?->id,
                'ip_address' => $request?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $excepcion) {
            \Log::error('AuditLookupService: no se pudo registrar la consulta', [
                'lookup_type' => $lookupType,
                'error' => $excepcion->getMessage(),
            ]);
        }
    }
}
