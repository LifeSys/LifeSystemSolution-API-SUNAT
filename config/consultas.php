<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor activo
    |--------------------------------------------------------------------------
    |
    | Feature flag para elegir el proveedor de consultas de identidad (DNI/RUC)
    | sin tocar código. Ej: cambiar de "apiperu" a otro proveedor en producción
    | solo requiere modificar esta variable de entorno y redesplegar.
    |
    */
    'default_provider' => env('CONSULTA_PROVIDER', 'apiperu'),

    /*
    |--------------------------------------------------------------------------
    | Proveedores disponibles
    |--------------------------------------------------------------------------
    |
    | Cada proveedor tiene su propia configuración de conexión. Al agregar un
    | proveedor nuevo, solo se añade una entrada aquí y una clase que implemente
    | App\Consultas\Contracts\IdentityProviderInterface — el resto del sistema
    | (controller, service, requests) no cambia.
    |
    */
    'providers' => [

        'apiperu' => [
            'base_url' => env('APIPERU_BASE_URL', 'https://apiperu.dev/api'),
            'token' => env('APIPERU_TOKEN', ''),
            'timeout' => (int) env('APIPERU_TIMEOUT', 10),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache de resultados
    |--------------------------------------------------------------------------
    |
    | El cache es independiente por tipo de consulta y NO depende del tenant:
    | el nombre asociado a un DNI es el mismo sin importar quién pregunte, así
    | que compartir el cache entre tenants ahorra cuota del proveedor.
    |
    */
    'cache' => [
        'dni_minutes' => (int) env('CACHE_DNI_MINUTES', 1440),
        'ruc_minutes' => (int) env('CACHE_RUC_MINUTES', 1440),
        'dni_ruc_minutes' => (int) env('CACHE_DNI_RUC_MINUTES', 1440),
    ],

];
