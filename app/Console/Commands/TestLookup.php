<?php

namespace App\Console\Commands;

use App\Consultas\Exceptions\ConsultaException;
use App\Consultas\Services\ConsultaService;
use Illuminate\Console\Command;

class TestLookup extends Command
{
    protected $signature = 'consultas:test {numero} {--tipo=ruc : dni|ruc|dni_ruc}';

    protected $description = 'Prueba una consulta DNI/RUC contra el proveedor activo (ApiPeru.dev por defecto)';

    public function handle(ConsultaService $consultas): int
    {
        $numero = $this->argument('numero');
        $tipo = $this->option('tipo');

        $this->line('Proveedor activo : ' . config('consultas.default_provider'));
        $this->line("Tipo de consulta : {$tipo}");
        $this->line("Número           : {$numero}");
        $this->line('');

        try {
            $resultado = match ($tipo) {
                'dni' => $consultas->consultarDni($numero),
                'ruc' => $consultas->consultarRuc($numero),
                'dni_ruc' => $consultas->consultarDniRuc($numero),
                default => throw new \InvalidArgumentException("Tipo inválido: {$tipo}. Usa dni, ruc o dni_ruc."),
            };

            $this->info('Resultado:');
            $this->line(json_encode($resultado->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (ConsultaException $excepcion) {
            $this->error("Error ({$excepcion->httpStatus}): " . $excepcion->getMessage());

            return self::FAILURE;
        }
    }
}
