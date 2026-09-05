<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Étapes 1 et 2 de l'inscription (§4) : la casquette choisie, puis les
 * informations. Le numéro est normalisé **avant** validation, sinon l'unicité
 * porterait sur la forme saisie et laisserait passer des doublons.
 */
final class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Telephone::normaliser((string) $this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')],
            // Jauge de robustesse du §4 : 8 caractères, une majuscule, un chiffre.
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'regex:/[A-Z]/', 'regex:/\d/'],
            'is_technician' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.unique' => 'Un compte existe déjà avec ce numéro. Connecte-toi ou utilise « mot de passe oublié ».',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nom complet',
            'phone' => 'numéro de téléphone',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }
}
