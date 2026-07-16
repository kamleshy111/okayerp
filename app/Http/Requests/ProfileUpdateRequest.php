<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'max:12'],
            'address' => ['nullable',],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'pin_code' => ['nullable', 'string', 'max:10'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'profile_photo' => ['nullable', 'max:2048'], // 2MB max
            'ledger_pin' => ['nullable', 'string', 'digits:4'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:255'],
            'invoice_title_without_gst' => ['nullable', 'string', 'max:255'],
            'invoice_title_with_gst' => ['nullable', 'string', 'max:255'],
            'invoice_print_size' => ['nullable', 'string', 'in:a4,a5,thermal'],
            'hide_bank_details' => ['nullable', 'boolean'],
        ];
    }
}
