<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Domain\Tickets\Actions\CreateTicket;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Publication d'une demande d'intervention (§8.1).
 *
 * L'appartenance de l'adresse au client n'est pas vérifiée ici mais dans
 * l'action de domaine : la règle est métier, et elle doit valoir aussi quand
 * l'appel vient du back-office plutôt que du mobile.
 */
final class TicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'problem_description' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.CreateTicket::MAX_PHOTOS],
            'photos.*' => ['string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photos.max' => 'Trois photos au maximum.',
            'service_id.exists' => 'Cette prestation n\'existe pas.',
            'address_id.exists' => 'Cette adresse n\'existe pas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'service_id' => 'prestation',
            'address_id' => 'adresse',
            'problem_description' => 'description du problème',
        ];
    }
}
