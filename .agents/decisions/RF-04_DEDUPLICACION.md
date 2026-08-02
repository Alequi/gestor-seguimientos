# RF-04 — Identidad y conflictos durante la deduplicación

## Estado

Decisión funcional confirmada por el usuario el 2 de agosto de 2026.

## Decisión

- RF-04 se aplica únicamente a las filas cuyo estado ya ha sido reconocido como `Solicitada`.
- La identidad temporal se construye con la agencia ajustada a cuatro dígitos y el albarán. Para comparar ambos campos se eliminan únicamente sus espacios exteriores.
- Por tanto, `304 + 14910300` y `0304 + 14910300` representan la misma expedición.
- Esta normalización solo construye la clave de deduplicación. Los valores originales de la fila conservada no se modifican.
- Si las filas de una misma identidad tienen la misma fecha, referencia y código postal, se conserva la primera según el orden del CSV.
- Si difieren en fecha, referencia o código postal, ninguna se selecciona automáticamente. El grupo completo se excluye de las candidatas procesables y se señala como conflicto para revisión.

## Motivo

La agencia debe llegar como `0304` a la futura URL de seguimiento, por lo que las variantes con y sin cero inicial no pueden representar expediciones distintas. Cuando otros datos de la misma identidad se contradicen, escoger una fila silenciosamente podría provocar una asociación incorrecta con Shopify.
