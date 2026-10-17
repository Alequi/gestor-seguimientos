<?php

namespace App\Application\Actions;

use App\Application\Data\ExecutionReportRow;
use RuntimeException;

final class GenerateExecutionReportCsv
{
    private const HEADERS = [
        'Referencia NACEX',
        'Agencia',
        'Albarán',
        'Código postal',
        'Fecha alta',
        'Estado NACEX',
        'URL de seguimiento',
        'Resultado del matching',
        'Pedido Shopify',
        'Cliente Shopify',
        'Fecha pedido',
        'Estado previo del pedido',
        'Resultado del cumplimiento',
        'Notificación solicitada',
        'Fecha de procesamiento',
        'Mensaje de error',
    ];

    /** @param list<ExecutionReportRow> $rows */
    public function handle(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('No se pudo crear el informe temporal.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, self::HEADERS);

            foreach ($rows as $row) {
                $this->writeRow($stream, array_map($this->safeCell(...), $row->values()));
            }

            rewind($stream);
            $contents = stream_get_contents($stream);

            if ($contents === false) {
                throw new RuntimeException('No se pudo leer el informe temporal.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream @param list<string> $values */
    private function writeRow($stream, array $values): void
    {
        if (fputcsv($stream, $values, ';', '"', '', "\r\n") === false) {
            throw new RuntimeException('No se pudo escribir el informe temporal.');
        }
    }

    private function safeCell(string $value): string
    {
        $trimmed = ltrim($value);

        return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)
            ? "'".$value
            : $value;
    }
}
