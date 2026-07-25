<?php

namespace App\Http\Controllers\Api\V1\Consultas;

use App\Consultas\Services\ConsultaService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Consultas\ConsultarDniRequest;
use App\Http\Requests\Api\V1\Consultas\ConsultarDniRucRequest;
use App\Http\Requests\Api\V1\Consultas\ConsultarRucRequest;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConsultaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConsultaService $consultas,
    ) {
    }

    public function dni(ConsultarDniRequest $request): JsonResponse
    {
        $resultado = $this->consultas->consultarDni(
            dni: $request->string('dni')->toString(),
            tenantId: $request->get('tenant')?->id,
            request: $request,
        );

        return $this->success($resultado->toArray(), 'Consulta realizada correctamente.');
    }

    public function ruc(ConsultarRucRequest $request): JsonResponse
    {
        $resultado = $this->consultas->consultarRuc(
            ruc: $request->string('ruc')->toString(),
            tenantId: $request->get('tenant')?->id,
            request: $request,
        );

        return $this->success($resultado->toArray(), 'Consulta realizada correctamente.');
    }

    public function dniRuc(ConsultarDniRucRequest $request): JsonResponse
    {
        $resultado = $this->consultas->consultarDniRuc(
            dni: $request->string('dni')->toString(),
            tenantId: $request->get('tenant')?->id,
            request: $request,
        );

        return $this->success($resultado->toArray(), 'Consulta realizada correctamente.');
    }
}
