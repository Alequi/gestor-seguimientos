<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\File;

final class UploadNacexCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'csv' => self::csvRules(),
        ];
    }

    /** @return list<mixed> */
    public static function csvRules(): array
    {
        return [
            'bail',
            'required',
            File::types([
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
            ])
                ->extensions(['csv'])
                ->max((int) config('nacex.csv.max_size_kilobytes')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxFileSizeMb = round((int) config('nacex.csv.max_size_kilobytes') / 1024, 2);

        return [
            'csv.required' => 'Selecciona un archivo CSV de NACEX.',
            'csv.file' => 'No se ha recibido un archivo válido.',
            'csv.mimetypes' => 'El archivo debe contener datos CSV válidos.',
            'csv.extensions' => 'El archivo debe tener extensión .csv.',
            'csv.max' => "El archivo CSV no puede superar {$maxFileSizeMb} MB.",
            'csv.uploaded' => 'No se ha podido subir el archivo CSV.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'El archivo CSV no es válido.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
