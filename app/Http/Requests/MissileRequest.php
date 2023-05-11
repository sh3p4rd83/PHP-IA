<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MissileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge(['partie_id' =>$this->route('partie_id')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            "partie_id" => "required|exists:App\Models\Partie,id"
        ];
    }
}
