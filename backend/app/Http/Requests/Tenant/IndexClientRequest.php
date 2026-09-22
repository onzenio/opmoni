<?php

namespace App\Http\Requests\Tenant;

use App\Enums\ClientStatus;
use App\Enums\TaxRegime;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Client::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ClientStatus::class)],
            'tax_regime' => ['sometimes', Rule::enum(TaxRegime::class)],
            'sort' => ['sometimes', Rule::in(['name', 'tax_id', 'status', 'tax_regime', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
