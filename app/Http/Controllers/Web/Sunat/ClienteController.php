<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Consultas\Exceptions\ConsultaException;
use App\Consultas\Services\ConsultaService;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ConsultaService $consultas,
    ) {
    }

    public function index(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $clientes = Client::where('tenant_id', $tenant->id)
            ->when(request('q'), fn ($query, $q) =>
                $query->where('razon_social', 'ilike', "%{$q}%")
                      ->orWhere('numero_documento', 'like', "%{$q}%")
            )
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($c) => [
                'id'               => $c->id,
                'tipo_documento'   => $c->tipo_documento,
                'numero_documento' => $c->numero_documento,
                'razon_social'     => $c->razon_social,
                'nombre_comercial' => $c->nombre_comercial,
                'direccion'        => $c->direccion,
                'email'            => $c->email,
                'telefono'         => $c->telefono,
            ]);

        return Inertia::render('sunat/clientes/index', [
            'clientes' => $clientes,
            'filtros'  => ['q' => request('q', '')],
            'tenant'   => ['environment' => $tenant->environment ?? 'beta'],
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();

        $data = $request->validate([
            'tipo_documento'   => 'required|string|max:1',
            'numero_documento' => 'required|string|max:15',
            'razon_social'     => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion'        => 'nullable|string|max:500',
            'email'            => 'nullable|email|max:255',
            'telefono'         => 'nullable|string|max:20',
        ]);

        Client::updateOrCreate(
            [
                'tenant_id'        => $tenant->id,
                'tipo_documento'   => $data['tipo_documento'],
                'numero_documento' => $data['numero_documento'],
            ],
            array_merge($data, ['tenant_id' => $tenant->id])
        );

        return back()->with('success', 'Cliente guardado correctamente.');
    }

    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant  = auth()->user()->tenants()->firstOrFail();
        $cliente = Client::where('tenant_id', $tenant->id)->findOrFail($id);

        $data = $request->validate([
            'razon_social'     => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion'        => 'nullable|string|max:500',
            'email'            => 'nullable|email|max:255',
            'telefono'         => 'nullable|string|max:20',
        ]);

        $cliente->update($data);

        return back()->with('success', 'Cliente actualizado.');
    }

    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        Client::where('tenant_id', $tenant->id)->findOrFail($id)->delete();

        return back()->with('success', 'Cliente eliminado.');
    }

    public function lookupRuc(Request $request): \Illuminate\Http\JsonResponse
    {
        $numero = trim($request->input('numero', ''));

        if (strlen($numero) < 8) {
            return response()->json(['error' => 'Número muy corto'], 422);
        }

        $tenant = auth()->user()->tenants()->first();

        try {
            $esRuc = strlen($numero) === 11;

            $resultado = $esRuc
                ? $this->consultas->consultarRuc($numero, tenantId: $tenant?->id, request: $request)
                : $this->consultas->consultarDni($numero, tenantId: $tenant?->id, request: $request);

            // Se conserva el shape exacto que ya espera el frontend Inertia actual.
            return response()->json([
                'razon_social' => $resultado->nombreORazonSocial,
                'direccion' => $resultado->direccion ?? '',
                'estado' => $resultado->estado ?? '',
                'condicion' => $resultado->condicion ?? '',
            ]);
        } catch (ConsultaException $excepcion) {
            return response()->json(['error' => $excepcion->getMessage()], $excepcion->httpStatus);
        }
    }
}
