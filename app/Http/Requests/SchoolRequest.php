<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('school') ?? $this->route('id');

        // Creating and updating are different rights: an instructor may edit
        // their own school without being able to add new ones.
        if (! $school) {
            return $this->user()?->can('create', School::class) ?? false;
        }

        $school = $school instanceof School ? $school : School::findOrFail($school);

        return $this->user()?->can('update', $school) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('school')?->id ?? $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Two schools sharing a name makes every "which school?" select
                // ambiguous. Archived ones keep their name.
                Rule::unique('schools', 'name')->ignore($id)->whereNull('deleted_at'),
            ],
            'shortname' => [
                'nullable', 'string', 'max:50',
                Rule::unique('schools', 'shortname')->ignore($id)->whereNull('deleted_at'),
            ],
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:50',
            'zip' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'url' => 'nullable|url|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Another school already has that name.',
            'shortname.unique' => 'Another school already uses that short name.',
            'url.url' => 'The website needs to be a full address, including https://',
        ];
    }

    protected function prepareForValidation(): void
    {
        // A bare domain is what people type; make it a URL rather than
        // rejecting them over a missing scheme.
        $url = trim((string) $this->input('url'));

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $this->merge(['url' => 'https://'.$url]);
        }
    }
}
