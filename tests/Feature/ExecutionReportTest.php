<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExecutionReportTest extends TestCase
{
    public function test_home_page_exposes_the_report_endpoint(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'csvUpload.reportUrl',
                route('reports.execution'),
            ));
    }

    public function test_it_downloads_the_temporary_execution_report_without_caching_it(): void
    {
        $response = $this->postJson(route('reports.execution'), [
            'rows' => [$this->reportRow()],
        ]);

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename="informe-ejecucion-nacex.csv"')
            ->assertHeader('cache-control', 'no-store, private');

        $contents = $response->getContent();
        $lines = explode("\r\n", substr($contents, 3));
        $headers = str_getcsv($lines[0], ';', '"', '');
        $values = str_getcsv($lines[1], ';', '"', '');

        $this->assertSame('Referencia NACEX', $headers[0]);
        $this->assertSame('Albarán', $headers[2]);
        $this->assertSame('Peña Muñoz', $values[0]);
        $this->assertSame('0304', $values[1]);
        $this->assertSame('0014910300', $values[2]);
        $this->assertSame('COMPLETED', $values[12]);
        $this->assertSame('SÍ', $values[13]);
        $this->assertSame('17/10/2026 12:30:45', $values[14]);
    }

    public function test_it_rejects_an_empty_report(): void
    {
        $this->postJson(route('reports.execution'), ['rows' => []])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se puede generar el informe.')
            ->assertJsonValidationErrors('rows');
    }

    /** @return array<string, string> */
    private function reportRow(): array
    {
        return [
            'reference' => 'Peña Muñoz',
            'originAgency' => '0304',
            'trackingNumber' => '0014910300',
            'postalCode' => '08019',
            'registrationDate' => '01/08/2026',
            'nacexStatus' => 'Solicitada',
            'trackingUrl' => 'https://www.nacex.es/seguimientoDetalle.do?numero_albaran=0014910300',
            'matchStatus' => 'MATCHED_SAFE',
            'shopifyOrderName' => '#TEST1',
            'shopifyCustomer' => 'Peña Muñoz',
            'shopifyOrderDate' => '2026-08-01T10:00:00Z',
            'previousOrderStatus' => 'UNFULFILLED',
            'fulfillmentResult' => 'COMPLETED',
            'notificationRequested' => 'SÍ',
            'processedAt' => '17/10/2026 12:30:45',
            'errorMessage' => '',
        ];
    }
}
