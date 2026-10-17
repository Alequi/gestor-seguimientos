<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class DownloadExecutionReportRequest extends FormRequest
{
    private const FIELDS = [
        'reference',
        'originAgency',
        'trackingNumber',
        'postalCode',
        'registrationDate',
        'nacexStatus',
        'trackingUrl',
        'matchStatus',
        'shopifyOrderName',
        'shopifyCustomer',
        'shopifyOrderDate',
        'previousOrderStatus',
        'fulfillmentResult',
        'notificationRequested',
        'processedAt',
        'errorMessage',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'rows' => ['required', 'array', 'min:1', 'max:1000'],
        ];

        foreach (self::FIELDS as $field) {
            $rules["rows.*.$field"] = ['present', 'nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rows.required' => 'No hay resultados para generar el informe.',
            'rows.min' => 'No hay resultados para generar el informe.',
            'rows.max' => 'El informe no puede contener más de 1.000 expediciones.',
            'rows.*.*' => 'Los datos temporales del informe no son válidos.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No se puede generar el informe.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
