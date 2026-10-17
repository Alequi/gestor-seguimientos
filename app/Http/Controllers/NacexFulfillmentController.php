<?php

namespace App\Http\Controllers;

use App\Application\Actions\ExecuteSelectedNacexShipments;
use App\Application\Data\SelectedShipment;
use App\Application\Exceptions\InvalidNacexCsv;
use App\Http\Requests\ExecuteNacexFulfillmentsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class NacexFulfillmentController extends Controller
{
    public function __invoke(
        ExecuteNacexFulfillmentsRequest $request,
        ExecuteSelectedNacexShipments $executeShipments,
    ): JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->file('csv');
        $validated = $request->validated();
        $selections = array_map(
            static fn (array $selection): SelectedShipment => new SelectedShipment(
                originAgency: $selection['originAgency'],
                trackingNumber: $selection['trackingNumber'],
                orderId: $selection['orderId'],
                assignment: $selection['assignment'],
            ),
            $validated['selections'],
        );

        try {
            $results = $executeShipments->handle(
                path: $file->getPathname(),
                originalName: $file->getClientOriginalName(),
                sizeInBytes: $file->getSize(),
                selections: $selections,
                notifyCustomer: (bool) $validated['notifyCustomer'],
            );
        } catch (InvalidNacexCsv $exception) {
            return response()->json([
                'message' => 'El archivo CSV no es válido.',
                'errors' => ['csv' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'data' => [
                'results' => array_map(
                    static fn ($result): array => $result->toArray(),
                    $results,
                ),
            ],
        ]);
    }
}
