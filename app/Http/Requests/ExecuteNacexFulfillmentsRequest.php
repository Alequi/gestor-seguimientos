<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class ExecuteNacexFulfillmentsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('selections'))) {
            $decoded = json_decode($this->input('selections'), true);
            $this->merge(['selections' => is_array($decoded) ? $decoded : null]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'csv' => UploadNacexCsvRequest::csvRules(),
            'notifyCustomer' => ['required', 'boolean'],
            'selections' => ['required', 'array', 'min:1', 'max:1000'],
            'selections.*.originAgency' => ['required', 'string', 'regex:/^[0-9]{4}$/D'],
            'selections.*.trackingNumber' => ['required', 'string'],
            'selections.*.orderId' => ['required', 'string', 'regex:/^gid:\/\/shopify\/Order\/[1-9][0-9]*$/D'],
            'selections.*.assignment' => ['required', 'string', Rule::in(['AUTOMATIC', 'MANUAL'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'selections.required' => 'Selecciona al menos una expedición.',
            'selections.array' => 'La selección de expediciones no es válida.',
            'selections.min' => 'Selecciona al menos una expedición.',
            'selections.max' => 'No se pueden procesar más de 1.000 expediciones.',
            'notifyCustomer.required' => 'Indica si Shopify debe notificar al cliente.',
            'notifyCustomer.boolean' => 'La opción de notificación no es válida.',
            'selections.*.originAgency.regex' => 'La agencia seleccionada no es válida.',
            'selections.*.orderId.regex' => 'El pedido seleccionado no es válido.',
            'selections.*.assignment.in' => 'El tipo de asignación no es válido.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No se puede iniciar el cierre de pedidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
