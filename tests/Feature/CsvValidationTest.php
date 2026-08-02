<?php

namespace Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvValidationTest extends TestCase
{
    private const VALID_CSV = <<<'CSV'
Ag. Origen;Albarán;Fecha alta;Referencia;CP Ent.;Estado
0304;0014910300;01/08/2026;Peña Muñoz;08019;Solicitada
CSV;

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
            ->assertJsonPath('data.encoding', 'UTF-8')
            ->assertJsonPath('data.headers.0', 'Ag. Origen')
            ->assertJsonPath('data.rows.0.originAgency', '0304')
            ->assertJsonPath('data.rows.0.trackingNumber', '0014910300')
            ->assertJsonPath('data.rows.0.registrationDate', '01/08/2026')
            ->assertJsonPath('data.rows.0.reference', 'Peña Muñoz')
            ->assertJsonPath('data.rows.0.postalCode', '08019')
            ->assertJsonPath('data.rows.0.status', 'Solicitada');
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
            ->assertJsonCount(0, 'data.rows');
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
