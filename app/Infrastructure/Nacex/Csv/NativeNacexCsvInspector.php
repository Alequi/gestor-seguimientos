<?php

namespace App\Infrastructure\Nacex\Csv;

use App\Application\Contracts\NacexCsvInspector;
use App\Application\Data\NacexCsvRow;
use App\Application\Data\NacexCsvValidationResult;
use App\Application\Exceptions\InvalidNacexCsv;

final readonly class NativeNacexCsvInspector implements NacexCsvInspector
{
    /**
     * @param  list<string>  $encodings
     * @param  list<string>  $requiredHeaders
     */
    public function __construct(
        private string $delimiter,
        private array $encodings,
        private array $requiredHeaders,
        private int $maxRows,
    ) {}

    public function inspect(
        string $path,
        string $originalName,
        int $sizeInBytes,
    ): NacexCsvValidationResult {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidNacexCsv('No se ha podido leer el archivo CSV.');
        }

        if ($contents === '') {
            throw new InvalidNacexCsv('El archivo CSV está vacío.');
        }

        if (str_contains($contents, "\0")) {
            throw new InvalidNacexCsv('El archivo no contiene texto CSV válido.');
        }

        [$normalizedContents, $encoding] = $this->normalizeEncoding($contents);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidNacexCsv('No se ha podido preparar el archivo CSV para su lectura.');
        }

        try {
            fwrite($stream, $normalizedContents);
            rewind($stream);

            [$headers, $headerPositions] = $this->readHeaders($stream);
            $rows = $this->readDataRows($stream, $headerPositions);
        } finally {
            fclose($stream);
        }

        return new NacexCsvValidationResult(
            fileName: $originalName,
            sizeInBytes: $sizeInBytes,
            rowCount: count($rows),
            encoding: $encoding,
            headers: $headers,
            rows: $rows,
        );
    }

    /**
     * @return array{string, string}
     */
    private function normalizeEncoding(string $contents): array
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return [substr($contents, 3), 'UTF-8'];
        }

        foreach ($this->encodings as $encoding) {
            if (! mb_check_encoding($contents, $encoding)) {
                continue;
            }

            return [
                $encoding === 'UTF-8'
                    ? $contents
                    : mb_convert_encoding($contents, 'UTF-8', $encoding),
                $encoding,
            ];
        }

        throw new InvalidNacexCsv(
            'La codificación del archivo no es compatible. Usa UTF-8, Windows-1252 o ISO-8859-1.',
        );
    }

    /**
     * @param  resource  $stream
     * @return array{list<string>, array<string, int>}
     */
    private function readHeaders($stream): array
    {
        $row = fgetcsv($stream, null, $this->delimiter, '"', '');

        if ($row === false || $this->isEmptyRow($row)) {
            throw new InvalidNacexCsv('El archivo CSV no contiene una fila de cabeceras.');
        }

        if (count($row) === 1) {
            throw new InvalidNacexCsv('El separador del archivo debe ser punto y coma (;).');
        }

        $headerRow = array_map(
            static fn ($header): string => trim((string) $header),
            $row,
        );
        $headers = array_values(array_filter(
            $headerRow,
            static fn (string $header): bool => $header !== '',
        ));

        $missingHeaders = array_values(array_diff($this->requiredHeaders, $headers));

        if ($missingHeaders !== []) {
            throw new InvalidNacexCsv(sprintf(
                'El archivo no contiene todas las columnas necesarias. Faltan: %s.',
                implode(', ', $missingHeaders),
            ));
        }

        $headerPositions = [];

        foreach ($this->requiredHeaders as $requiredHeader) {
            $position = array_search($requiredHeader, $headerRow, true);

            if ($position !== false) {
                $headerPositions[$requiredHeader] = $position;
            }
        }

        return [$headers, $headerPositions];
    }

    /**
     * @param  resource  $stream
     * @param  array<string, int>  $headerPositions
     * @return list<NacexCsvRow>
     */
    private function readDataRows($stream, array $headerPositions): array
    {
        $rows = [];

        while (($row = fgetcsv($stream, null, $this->delimiter, '"', '')) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            if (count($rows) >= $this->maxRows) {
                throw new InvalidNacexCsv(sprintf(
                    'El archivo supera el máximo de %s filas de datos.',
                    number_format($this->maxRows, 0, ',', '.'),
                ));
            }

            $rows[] = new NacexCsvRow(
                originAgency: $this->valueAt($row, $headerPositions['Ag. Origen']),
                trackingNumber: $this->valueAt($row, $headerPositions['Albarán']),
                registrationDate: $this->valueAt($row, $headerPositions['Fecha alta']),
                reference: $this->valueAt($row, $headerPositions['Referencia']),
                postalCode: $this->valueAt($row, $headerPositions['CP Ent.']),
                status: $this->valueAt($row, $headerPositions['Estado']),
            );
        }

        if ($rows === []) {
            throw new InvalidNacexCsv('El archivo CSV no contiene filas de datos.');
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function valueAt(array $row, int $position): string
    {
        return (string) ($row[$position] ?? '');
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
