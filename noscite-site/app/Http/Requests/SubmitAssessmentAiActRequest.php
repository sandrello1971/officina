<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssessmentAiActRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'required|string|max:255',
            'role' => 'nullable|string|max:150',
            'gdpr_consent' => 'required|accepted',
            'scores' => 'required|array',
            'scores.tools' => 'required|integer|between:1,4',
            'scores.governance' => 'required|integer|between:1,4',
            'scores.skills' => 'required|integer|between:1,4',
            'scores.processes' => 'required|integer|between:1,4',
            'scores.compliance' => 'required|integer|between:1,4',
            'notes' => 'nullable|array',
            'notes.tools' => 'nullable|string|max:5000',
            'notes.governance' => 'nullable|string|max:5000',
            'notes.skills' => 'nullable|string|max:5000',
            'notes.processes' => 'nullable|string|max:5000',
            'notes.compliance' => 'nullable|string|max:5000',
            // Honeypot anti-bot: deve restare vuoto
            'website' => 'prohibited',
        ];
    }

    public function messages(): array
    {
        return [
            'gdpr_consent.accepted' => 'Devi accettare il trattamento dei dati per inviare il modulo.',
            'website.prohibited' => 'Bot detection.',
            'email.email' => 'Inserisci un indirizzo email valido.',
        ];
    }
}
