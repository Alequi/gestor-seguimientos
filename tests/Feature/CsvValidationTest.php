<?php

namespace Tests\Feature;

use App\Application\Contracts\ShopifyOrderReader;
use App\Application\Data\ShopifyOrderSnapshot;
use App\Domain\Matching\OrderDateWindow;
use DateTimeImmutable;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvValidationTest extends TestCase
{
    private const VALID_CSV = <<<'CSV'
Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado
0304;0014910300;01/08/2026;Peña Muñoz;08019;Solicitada
CSV;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ShopifyOrderReader::class, new class implements ShopifyOrderReader
        {
            public function findCreatedIn(OrderDateWindow $window): array
            {
                return [];
            }
        });
    }

    public function test_home_page_exposes_the_csv_upload_contract(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('homeUrl')
                ->where('csvUpload.maxFileSizeMb', 5)
                ->where('csvUpload.maxRows', 1000)
                ->where('csvUpload.requiredHeaders', [
                    'Ag. Origen',
                    'Albarán',
                    'Fecha alta',
                    'Referencia',
                    'CP Ent.',
                    'Estado',
                ])
                ->has('csvUpload.validationUrl')
            );
    }

    public function test_a_valid_csv_can_be_validated(): void
    {
        $response = $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile(self::VALID_CSV),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.fileName', 'expediciones.csv')
            ->assertJsonPath('data.rowCount', 1)
            ->assertJsonPath('data.requestedRowCount', 1)
            ->assertJsonPath('data.processableRowCount', 1)
            ->assertJsonPath('data.duplicateConflictCount', 0)
            ->assertJsonPath('data.encoding', 'UTF-8')
            ->assertJsonPath('data.headers.0', 'Ag. Origen')
            ->assertJsonPath('data.rows.0.originAgency', '0304')
            ->assertJsonPath('data.rows.0.trackingNumber', '0014910300')
            ->assertJsonPath('data.rows.0.registrationDate', '01/08/2026')
            ->assertJsonPath('data.rows.0.reference', 'Peña Muñoz')
            ->assertJsonPath('data.rows.0.normalizedReference', 'peña muñoz')
            ->assertJsonPath('data.rows.0.postalCode', '08019')
            ->assertJsonPath('data.rows.0.status', 'Solicitada')
            ->assertJsonPath('data.rows.0.matchStatus', 'NO_MATCH')
            ->assertJsonPath(
                'data.rows.0.trackingUrl',
                'https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=0014910300&estado=4&internacional=0&externo=N',
            );
    }

    public function test_the_visible_csv_endpoint_returns_the_read_only_shopify_classification(): void
    {
        $this->app->instance(ShopifyOrderReader::class, new class implements ShopifyOrderReader
        {
            public function findCreatedIn(OrderDateWindow $window): array
            {
                return [new ShopifyOrderSnapshot(
                    id: 'gid://shopify/Order/1',
                    name: '#TEST1',
                    createdAt: new DateTimeImmutable('2026-08-01T10:00:00Z'),
                    cancelledAt: null,
                    fulfillmentStatus: 'UNFULFILLED',
                    shippingName: 'Peña Muñoz',
                    shippingPostalCode: '08019',
                    trackingNumbers: [],
                    fulfillmentCount: 0,
                    fulfillmentOrderStatuses: ['OPEN'],
                )];
            }
        });

        $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile(self::VALID_CSV),
        ])
            ->assertOk()
            ->assertJsonPath('data.rows.0.matchStatus', 'MATCHED_SAFE')
            ->assertJsonPath('data.rows.0.shopifyOrder.id', 'gid://shopify/Order/1')
            ->assertJsonPath('data.rows.0.shopifyOrder.name', '#TEST1')
            ->assertJsonCount(0, 'data.rows.0.candidateOrders')
            ->assertJsonPath('data.rows.0.errorMessage', null);
    }

    public function test_only_requested_rows_are_returned_as_candidates(): void
    {
        $csv = implode("\n", [
            'Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado',
            '0304;0014910300;01/08/2026;Ana García;08019;En tránsito',
            '0304;0014910301;01/08/2026;Bea Muñoz;08020; SOLICITADA ',
            '0304;0014910302;01/08/2026;Celia Núñez;08021;Entregado',
            '0304;0014910303;01/08/2026;Diana Peña;08022;solicitada',
        ])."\n";

        $response = $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile($csv),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.rowCount', 4)
            ->assertJsonPath('data.requestedRowCount', 2)
            ->assertJsonPath('data.processableRowCount', 2)
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.trackingNumber', '0014910301')
            ->assertJsonPath('data.rows.0.status', ' SOLICITADA ')
            ->assertJsonPath('data.rows.1.trackingNumber', '0014910303')
            ->assertJsonMissing(['trackingNumber' => '0014910300'])
            ->assertJsonMissing(['trackingNumber' => '0014910302']);
    }

    public function test_a_valid_csv_without_requested_rows_returns_an_empty_candidate_list(): void
    {
        $csv = <<<'CSV'
Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado
0304;0014910300;01/08/2026;Ana García;08019;En tránsito
0304;0014910301;01/08/2026;Bea Muñoz;08020;Entregado
CSV;

        $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile($csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.rowCount', 2)
            ->assertJsonPath('data.requestedRowCount', 0)
            ->assertJsonPath('data.processableRowCount', 0)
            ->assertJsonCount(0, 'data.rows');
    }

    public function test_requested_rows_are_deduplicated_by_normalized_agency_and_tracking_number(): void
    {
        $csv = implode("\n", [
            'Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado',
            '304;14910300;01/08/2026;Ana García;08019;Solicitada',
            ' 0304 ; 14910300 ;01/08/2026;Ana García;08019; SOLICITADA ',
            '0304;14910301;01/08/2026;Bea Muñoz;08020;Solicitada',
            '0304;14910301;02/08/2026;Otra referencia;28020;En tránsito',
        ])."\n";

        $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile($csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.rowCount', 4)
            ->assertJsonPath('data.requestedRowCount', 3)
            ->assertJsonPath('data.processableRowCount', 2)
            ->assertJsonPath('data.duplicateConflictCount', 0)
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.originAgency', '0304')
            ->assertJsonPath('data.rows.0.trackingNumber', '14910300')
            ->assertJsonPath('data.rows.1.trackingNumber', '14910301');
    }

    public function test_conflicting_duplicates_are_excluded_and_returned_for_review(): void
    {
        $csv = implode("\n", [
            'Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado',
            '304;14910300;01/08/2026;Ana García;08019;Solicitada',
            '0304;14910300;01/08/2026;Ana García;28019;SOLICITADA',
        ])."\n";

        $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile($csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.requestedRowCount', 2)
            ->assertJsonPath('data.processableRowCount', 0)
            ->assertJsonPath('data.duplicateConflictCount', 1)
            ->assertJsonCount(0, 'data.rows')
            ->assertJsonCount(1, 'data.duplicateConflicts')
            ->assertJsonPath('data.duplicateConflicts.0.originAgency', '0304')
            ->assertJsonPath('data.duplicateConflicts.0.trackingNumber', '14910300')
            ->assertJsonCount(2, 'data.duplicateConflicts.0.rows')
            ->assertJsonPath('data.duplicateConflicts.0.rows.0.postalCode', '08019')
            ->assertJsonPath('data.duplicateConflicts.0.rows.1.postalCode', '28019');
    }

    public function test_processable_rows_are_normalized_after_deduplication(): void
    {
        $csv = <<<'CSV'
Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado
304;14910300;01/08/2026;  PEÑA-MUÑOZ, Álvarez  ;8019;Solicitada
CSV;

        $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile($csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.rows.0.originAgency', '0304')
            ->assertJsonPath('data.rows.0.postalCode', '08019')
            ->assertJsonPath('data.rows.0.reference', '  PEÑA-MUÑOZ, Álvarez  ')
            ->assertJsonPath('data.rows.0.normalizedReference', 'peña muñoz alvarez')
            ->assertJsonPath('data.rows.0.trackingNumber', '14910300')
            ->assertJsonPath(
                'data.rows.0.trackingUrl',
                'https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=14910300&estado=4&internacional=0&externo=N',
            );
    }

    public function test_a_csv_detected_as_plain_text_can_be_validated(): void
    {
        $response = $this->postJson(route('csv.validate'), [
            'csv' => $this->csvFile(self::VALID_CSV, 'text/plain'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.rowCount', 1);
    }

    public function test_the_csv_is_required(): void
    {
        $this->postJson(route('csv.validate'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El archivo CSV no es válido.')
            ->assertJsonValidationErrors('csv');
    }

    public function test_the_file_must_have_a_csv_extension(): void
    {
        $file = File::createWithContent('expediciones.txt', self::VALID_CSV)
            ->mimeType('text/plain');

        $this->postJson(route('csv.validate'), ['csv' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv');
    }

    public function test_the_file_cannot_exceed_five_megabytes(): void
    {
        $file = UploadedFile::fake()
            ->create('expediciones.csv', 5121, 'text/csv');

        $this->postJson(route('csv.validate'), ['csv' => $file])
            ->assertUnprocessable()
            ->assertJsonPath('errors.csv.0', 'El archivo CSV no puede superar 5 MB.');
    }

    public function test_missing_columns_are_reported_without_processing_the_file(): void
    {
        $file = $this->csvFile("Ag. Origen;Albarán;Fecha alta;CP Ent.\n0304;14910300;01/08/2026;03400\n");

        $this->postJson(route('csv.validate'), ['csv' => $file])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.csv.0',
                'El archivo no contiene todas las columnas necesarias. Faltan: Referencia, Estado.',
            );
    }

    private function csvFile(string $contents, string $mimeType = 'text/csv'): File
    {
        return File::createWithContent('expediciones.csv', $contents)
            ->mimeType($mimeType);
    }
}
