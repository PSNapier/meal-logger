<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportUserDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'file' => ['required', 'file', 'extensions:json', 'max:10240'],
        ];

        if ($this->routeIs('data.import.store')) {
            $rules['mode'] = ['required', Rule::in(['merge', 'overwrite'])];
        }

        return $rules;
    }
}
