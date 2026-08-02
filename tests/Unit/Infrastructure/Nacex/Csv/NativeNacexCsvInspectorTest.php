<?php

namespace Tests\Unit\Infrastructure\Nacex\Csv;

use App\Application\Data\NacexCsvValidationResult;
use App\Application\Exceptions\InvalidNacexCsv;
use App\Infrastructure\Nacex\Csv\NativeNacexCsvInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NativeNacexCsvInspectorTest extends TestCase
{
    private const HEADERS = [
        'Ag. Origen',
        'Albarán',
        'Fecha alta',
        'Referencia',
        'CP Ent.',
        'Estado',
    ];

    public function test_it_inspects_a_valid_utf8_csv_as_text(): void
    {
        $contents = implode(';', [...self::HEADERS, ''])."\n"
            .'0304;0014910300;01/08/2026;Peña Muñoz;08019;Solicitada;'."\n"
            .'0304;0014910301;01/08/2026;Ana García;03400;Solicitada;'."\n";

        $result = $this->inspect($contents);

        self::assertSame('expediciones.csv', $result->fileName);
        self::assertSame(strlen($contents), $result->sizeInBytes);
        self::assertSame(2, $result->rowCount);
        self::assertSame('UTF-8', $result->encoding);
        self::assertSame(self::HEADERS, $result->headers);
        self::assertSame('0304', $result->rows[0]->originAgency);
        self::assertSame('0014910300', $result->rows[0]->trackingNumber);
        self::assertSame('Peña Muñoz', $result->rows[0]->reference);
        self::assertSame('08019', $result->rows[0]->postalCode);
    }

    public function test_it_accepts_utf8_with_a_bom(): void
    {
        $contents = "\xEF\xBB\xBF".$this->validCsv();

        $result = $this->inspect($contents);

        self::assertSame('UTF-8', $result->encoding);
        self::assertSame(1, $result->rowCount);
    }

    public function test_it_converts_windows_1252_before_reading(): void
    {
        $utf8Contents = $this->validCsv(reference: 'Peña Muñoz');
        $windowsContents = mb_convert_encoding($utf8Contents, 'Windows-1252', 'UTF-8');

        $result = $this->inspect($windowsContents);

        self::assertSame('Windows-1252', $result->encoding);
        self::assertSame(1, $result->rowCount);
        self::assertSame('Peña Muñoz', $result->rows[0]->reference);
    }

    public function test_it_reads_iso_8859_1_compatible_content(): void
    {
        $utf8Contents = $this->validCsv(reference: 'Iñaki Núñez');
        $isoContents = mb_convert_encoding($utf8Contents, 'ISO-8859-1', 'UTF-8');

        $result = $this->inspect($isoContents);

        self::assertSame(1, $result->rowCount);
        self::assertSame('Iñaki Núñez', $result->rows[0]->reference);
    }

    public function test_it_reads_quoted_fields_and_never_converts_textual_numbers(): void
    {
        $contents = implode(';', self::HEADERS)."\n"
            .'0007;000000000000123456789;01/08/2026;"Muñoz; Peña";00123;Solicitada'."\n"
            .'0123;1.49103E+14;02/08/2026;Iñaki Núñez;08001;Solicitada'."\n";

        $result = $this->inspect($contents);

        self::assertSame('0007', $result->rows[0]->originAgency);
        self::assertSame('000000000000123456789', $result->rows[0]->trackingNumber);
        self::assertSame('Muñoz; Peña', $result->rows[0]->reference);
        self::assertSame('00123', $result->rows[0]->postalCode);
        self::assertSame('1.49103E+14', $result->rows[1]->trackingNumber);
    }

    #[DataProvider('invalidCsvProvider')]
    public function test_it_rejects_invalid_csv_content(string $contents, string $expectedMessage): void
    {
        $this->expectException(InvalidNacexCsv::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->inspect($contents);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCsvProvider(): iterable
    {
        yield 'empty file' => ['', 'El archivo CSV está vacío.'];

        yield 'wrong delimiter' => [
            implode(',', self::HEADERS)."\n0304,14910300,01/08/2026,Ana,03400,Solicitada\n",
            'El separador del archivo debe ser punto y coma (;).',
        ];

        yield 'missing headers' => [
            "Ag. Origen;Albarán;Fecha alta;CP Ent.\n0304;14910300;01/08/2026;03400\n",
            'El archivo no contiene todas las columnas necesarias. Faltan: Referencia, Estado.',
        ];

        yield 'no data rows' => [
            implode(';', self::HEADERS)."\n;;;;;\n",
            'El archivo CSV no contiene filas de datos.',
        ];
    }

    public function test_it_accepts_one_thousand_data_rows(): void
    {
        $result = $this->inspect($this->csvWithRows(1000));

        self::assertSame(1000, $result->rowCount);
        self::assertCount(1000, $result->rows);
    }

    public function test_it_rejects_row_one_thousand_and_one(): void
    {
        $this->expectException(InvalidNacexCsv::class);
        $this->expectExceptionMessage('El archivo supera el máximo de 1.000 filas de datos.');

        $this->inspect($this->csvWithRows(1001));
    }

    private function inspect(string $contents): NacexCsvValidationResult
    {
        $path = tempnam(sys_get_temp_dir(), 'nacex-csv-test-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        try {
            return $this->inspector()->inspect(
                path: $path,
                originalName: 'expediciones.csv',
                sizeInBytes: strlen($contents),
            );
        } finally {
            unlink($path);
        }
    }

    private function inspector(): NativeNacexCsvInspector
    {
        return new NativeNacexCsvInspector(
            delimiter: ';',
            encodings: ['UTF-8', 'Windows-1252', 'ISO-8859-1'],
            requiredHeaders: self::HEADERS,
            maxRows: 1000,
        );
    }

    private function validCsv(string $reference = 'Ana García'): string
    {
        return implode(';', self::HEADERS)."\n"
            ."0304;0014910300;01/08/2026;{$reference};08019;Solicitada\n";
    }

    private function csvWithRows(int $rowCount): string
    {
        $rows = [implode(';', self::HEADERS)];

        for ($index = 1; $index <= $rowCount; $index++) {
            $rows[] = "0304;{$index};01/08/2026;Cliente {$index};08019;Solicitada";
        }

        return implode("\n", $rows)."\n";
    }
}
