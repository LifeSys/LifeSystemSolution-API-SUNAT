<?php

namespace App\Http\Requests\Api\V1\Consultas;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarDniRucRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dni' => ['required', 'numeric', 'digits:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'El DNI es obligatorio.',
            'dni.numeric' => 'El DNI debe contener solo números.',
            'dni.digits' => 'El DNI debe tener exactamente 8 dígitos.',
        ];
    }
}
