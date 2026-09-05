<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Adresse du carnet client (§7.2).
 *
 * Les bornes de latitude et de longitude encadrent largement la Guinée. Elles
 * ne remplacent pas le test d'appartenance à une zone — c'est PostGIS qui
 * tranche — mais elles arrêtent d'emblée une coordonnée inversée ou nulle,
 * l'erreur la plus courante côté mobile.
 */
final class AdresseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $obligatoire = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'label' => [$obligatoire, 'string', 'max:60'],
            'formatted_address' => [$obligatoire, 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => [$obligatoire, 'numeric', 'between:7.0,13.0'],
            'longitude' => [$obligatoire, 'numeric', 'between:-15.5,-7.5'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'latitude.between' => 'Cette position ne se trouve pas en Guinée.',
            'longitude.between' => 'Cette position ne se trouve pas en Guinée.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'label' => 'libellé',
            'formatted_address' => 'adresse',
            'landmark' => 'point de repère',
        ];
    }
}
