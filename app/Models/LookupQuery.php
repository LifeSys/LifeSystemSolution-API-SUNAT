<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría de consultas de identidad/datos públicos
 * (DNI, RUC, y futuras: tipo de cambio, CPE, representantes legales, etc.).
 *
 * No reemplaza al cache técnico (Cache::remember): esta tabla existe para
 * poder responder "¿cuántas consultas hizo el tenant X hoy?" o "¿qué pasó
 * con la consulta de RUC del martes a las 10:20?", no para evitar llamadas
 * duplicadas al proveedor.
 */
class LookupQuery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'provider',
        'lookup_type',
        'document_number',
        'http_status',
        'response_time_ms',
        'cache_hit',
        'requested_by_user_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'cache_hit' => 'boolean',
        'created_at' => 'datetime',
    ];
}
