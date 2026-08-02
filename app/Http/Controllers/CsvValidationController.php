<?php

namespace App\Http\Controllers;

use App\Application\Actions\ValidateNacexCsv;
use App\Application\Exceptions\InvalidNacexCsv;
use App\Http\Requests\UploadNacexCsvRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class CsvValidationController extends Controller
{
    public function __invoke(
        UploadNacexCsvRequest $request,
        ValidateNacexCsv $validateNacexCsv,
    ): JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->file('csv');

        try {
            $result = $validateNacexCsv->handle(
                path: $file->getPathname(),
                originalName: $file->getClientOriginalName(),
                sizeInBytes: $file->getSize(),
            );
        } catch (InvalidNacexCsv $exception) {
            return response()->json([
                'message' => 'El archivo CSV no es válido.',
                'errors' => [
                    'csv' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }
}
