<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Domain\Catalog\Data\Specialty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Étape 3 de l'inscription : le dossier professionnel du technicien (§4).
 * Les trois pièces sont exigées ici — un dossier sans pièce d'identité ne peut
 * pas être examiné, et le laisser passer encombrerait la file de validation.
 */
final class DossierTechnicienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_technician === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'specialties' => ['required', 'array', 'min:1'],
            'specialties.*' => ['required', 'string', Rule::in(array_column(Specialty::cases(), 'value'))],
            'latitude' => ['required', 'numeric', 'between:7,13'],
            'longitude' => ['required', 'numeric', 'between:-15.5,-7.5'],
            'service_radius_km' => ['required', 'integer', 'between:1,30'],
            'id_doc_front_url' => ['required', 'string', 'max:500'],
            'id_doc_back_url' => ['required', 'string', 'max:500'],
            'selfie_url' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'specialties.required' => 'Choisis au moins une spécialité : plomberie ou électricité.',
            'latitude.between' => 'Le point de départ doit se situer en Guinée.',
            'longitude.between' => 'Le point de départ doit se situer en Guinée.',
            'id_doc_front_url.required' => 'Le recto de la pièce d\'identité est obligatoire.',
            'id_doc_back_url.required' => 'Le verso de la pièce d\'identité est obligatoire.',
            'selfie_url.required' => 'Le selfie est obligatoire : il permet de vérifier que la pièce est bien la tienne.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'specialties' => 'spécialités',
            'service_radius_km' => 'rayon d\'intervention',
        ];
    }
}
