<?php

namespace App\Http\Controllers;

use App\Application\Actions\GenerateExecutionReportCsv;
use App\Application\Data\ExecutionReportRow;
use App\Http\Requests\DownloadExecutionReportRequest;
use Illuminate\Http\Response;

final class ExecutionReportController extends Controller
{
    public function __invoke(
        DownloadExecutionReportRequest $request,
        GenerateExecutionReportCsv $generateReport,
    ): Response {
        $rows = array_map(
            static fn (array $row): ExecutionReportRow => new ExecutionReportRow(
                ...array_map(static fn ($value): string => (string) ($value ?? ''), $row),
            ),
            $request->validated('rows'),
        );

        return response($generateReport->handle($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="informe-ejecucion-nacex.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
