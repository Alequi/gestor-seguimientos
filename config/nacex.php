<?php

return [
    'csv' => [
        'delimiter' => ';',
        'max_size_kilobytes' => (int) env('NACEX_CSV_MAX_SIZE_KB', 5120),
        'max_rows' => (int) env('NACEX_CSV_MAX_ROWS', 1000),
        'encodings' => [
            'UTF-8',
            'Windows-1252',
            'ISO-8859-1',
        ],
        'required_headers' => [
            'Ag. Origen',
            'Albarán',
            'Fecha alta',
            'Referencia',
            'CP Ent.',
            'Estado',
        ],
    ],
];
