<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class ReinitialisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'regex:/[A-Z]/', 'regex:/\d/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.size' => 'Le code reçu par SMS comporte six chiffres.',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['phone' => 'numéro de téléphone', 'password' => 'mot de passe'];
    }
}
