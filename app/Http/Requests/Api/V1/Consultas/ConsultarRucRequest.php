<?php

namespace App\Http\Requests\Api\V1\Consultas;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarRucRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ruc' => ['required', 'numeric', 'digits:11'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.numeric' => 'El RUC debe contener solo números.',
            'ruc.digits' => 'El RUC debe tener exactamente 11 dígitos.',
        ];
    }
}
