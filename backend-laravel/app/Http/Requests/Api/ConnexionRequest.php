<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class ConnexionRequest extends FormRequest
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
            'password' => ['required', 'string', 'max:72'],
            // Nom de l'appareil : il apparaît dans la liste des sessions et
            // permet au porteur de reconnaître laquelle révoquer.
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'phone' => 'numéro de téléphone',
            'password' => 'mot de passe',
        ];
    }
}
