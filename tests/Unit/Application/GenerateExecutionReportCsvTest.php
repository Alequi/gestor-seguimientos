<?php

namespace Tests\Unit\Application;

use App\Application\Actions\GenerateExecutionReportCsv;
use App\Application\Data\ExecutionReportRow;
use PHPUnit\Framework\TestCase;

class GenerateExecutionReportCsvTest extends TestCase
{
    public function test_it_generates_utf8_semicolon_csv_and_escapes_spreadsheet_formulas(): void
    {
        $report = (new GenerateExecutionReportCsv)->handle([
            new ExecutionReportRow(
                reference: '=HYPERLINK("https://example.test";"Peña")',
                originAgency: '0304',
                trackingNumber: '0014910300',
                postalCode: '08019',
                registrationDate: '01/08/2026',
                nacexStatus: 'Solicitada',
                trackingUrl: 'https://www.nacex.es/seguimientoDetalle.do?numero_albaran=0014910300',
                matchStatus: 'MATCHED_SAFE',
                shopifyOrderName: '#TEST1',
                shopifyCustomer: 'Peña; Muñoz',
                shopifyOrderDate: '2026-08-01T10:00:00Z',
                previousOrderStatus: 'UNFULFILLED',
                fulfillmentResult: 'COMPLETED',
                notificationRequested: 'SÍ',
                processedAt: '17/10/2026 12:30:45',
                errorMessage: '',
            ),
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $report);

        $lines = explode("\r\n", substr($report, 3));
        $headers = str_getcsv($lines[0], ';', '"', '');
        $values = str_getcsv($lines[1], ';', '"', '');

        $this->assertCount(16, $headers);
        $this->assertSame('Referencia NACEX', $headers[0]);
        $this->assertSame('Mensaje de error', $headers[15]);
        $this->assertSame("'=HYPERLINK(\"https://example.test\";\"Peña\")", $values[0]);
        $this->assertSame('0014910300', $values[2]);
        $this->assertSame('Peña; Muñoz', $values[9]);
        $this->assertSame('SÍ', $values[13]);
    }
}
