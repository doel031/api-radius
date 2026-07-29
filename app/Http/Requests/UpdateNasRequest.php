<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nasId = $this->route('na') ?? $this->route('nas'); // Menyesuaikan nama parameter route

        return [
            'nasname'     => 'required|string|max:128|unique:nas,nasname,' . $nasId,
            'shortname'   => 'nullable|string|max:32',
            'type'        => 'nullable|string|max:30',
            'ports'       => 'nullable|integer',
            'secret'      => 'required|string|max:60',
            'server'      => 'nullable|string|max:64',
            'community'   => 'nullable|string|max:50',
            'description' => 'nullable|string|max:200',
        ];
    }
}