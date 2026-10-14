# Importación de CSV NACEX

## Estado

RF-01 a RF-06 implementados. La aplicación permite cargar, validar estructuralmente y leer un CSV como texto, conservar únicamente las filas `Solicitada`, deduplicarlas de forma segura, normalizar las candidatas y construir su URL de seguimiento NACEX, sin almacenarlo ni consultar Shopify.

## Alcance de RF-01

El usuario puede seleccionar o arrastrar un CSV de NACEX y solicitar su validación. En esta fase la aplicación:

1. Comprueba la extensión `.csv` y admite los MIME habituales con los que Windows/PHP identifica un CSV textual (`text/csv`, `text/plain`, `application/csv` y `application/vnd.ms-excel`). El contenido se valida después mediante su estructura.
2. Rechaza archivos mayores de 5 MB.
3. Lee `UTF-8`, `Windows-1252` e `ISO-8859-1`.
4. Exige el separador punto y coma (`;`).
5. Comprueba que la primera fila contiene las cabeceras.
6. Comprueba que existe al menos una fila de datos no vacía.
7. Rechaza archivos con más de 1.000 filas de datos, sin contar la cabecera ni líneas vacías.
8. Informa del nombre, tamaño, codificación y número de filas cuando el archivo es válido.

No consulta Shopify, filtra estados, normaliza valores, elimina duplicados ni construye seguimientos. Esas responsabilidades pertenecen a requisitos posteriores.

## Alcance de RF-02

Después de validar la estructura, la aplicación lee cada fila no vacía mediante las posiciones de las seis cabeceras obligatorias y devuelve:

- `Ag. Origen` como `originAgency`.
- `Albarán` como `trackingNumber`.
- `Fecha alta` como `registrationDate`.
- `Referencia` como `reference`.
- `CP Ent.` como `postalCode`.
- `Estado` como `status`.

Todos los campos se mantienen como cadenas UTF-8. En concreto:

- Se conservan tildes, `ñ`, `ü` y el resto de caracteres españoles.
- Agencia, albarán y código postal no se convierten a números y conservan sus ceros iniciales.
- Un albarán largo no se redondea ni pasa a notación científica.
- Un valor que ya contenga una notación como `1.49103E+14` se conserva literalmente como texto.
- El lector respeta el entrecomillado CSV, incluidos los separadores `;` dentro de un campo.
- Las columnas adicionales no forman parte del DTO de RF-02.

El CSV de referencia aportado para RF-07 confirma que `Fecha alta` usa `DD/MM/AAAA` en sus 40 filas. RF-02 sigue transportando ese valor como texto sin convertirlo ni validarlo; la interpretación estricta pertenece a la regla temporal de RF-07 documentada en [Búsqueda de pedidos en Shopify](BUSQUEDA_PEDIDOS.md). El CSV real no se copia al repositorio.

RF-02 no recorta ni normaliza los valores originales y no valida el formato individual de cada campo. El valor leído se conserva para que las transformaciones posteriores sean explícitas y comprobables.

## Alcance de RF-03

El caso de uso filtra las filas leídas y devuelve como candidatas únicamente aquellas cuyo estado normalizado equivale a `Solicitada`.

Para decidir la equivalencia del estado se ignoran:

- Mayúsculas y minúsculas.
- Espacios al principio y al final.
- Espacios duplicados, tabulaciones y separadores Unicode equivalentes.

El estado original de una fila candidata se mantiene sin modificar en su DTO; la representación normalizada solo se utiliza dentro de la regla de dominio. Los demás estados se excluyen de `rows` y no avanzan hacia RF-04 ni requisitos posteriores.

`rowCount` conserva el total de filas no vacías del archivo y `requestedRowCount` informa cuántas filas `Solicitada` quedan antes de deduplicar. En la salida propia de RF-03 ambas cantidades coinciden; el contrato HTTP completo deja de exigir esa igualdad cuando RF-04 aparta repeticiones o conflictos. RF-03 no deduplica, no normaliza agencia o código postal y no construye URLs.

## Alcance de RF-04

RF-04 se ejecuta después del filtro de estado y antes de la normalización general de RF-05. La identidad temporal de cada expedición se construye con:

```text
agencia ajustada a cuatro dígitos + albarán
```

Para construir esa identidad se eliminan únicamente los espacios exteriores de agencia y albarán, y la agencia se completa con ceros a la izquierda hasta cuatro caracteres. Así, `304 + 14910300` y `0304 + 14910300` se consideran la misma expedición. Los ceros del albarán se conservan y no se aplica ninguna conversión numérica.

Esta transformación se limita a la comparación: los seis valores originales del DTO no se modifican. Si varias filas de una identidad tienen exactamente la misma fecha, referencia y código postal, se conserva la primera según el orden del CSV. Las diferencias de formato del estado no crean conflicto porque todas las filas ya han superado RF-03.

Si las filas de una misma identidad difieren en fecha, referencia o código postal, ninguna avanza automáticamente. El grupo completo se devuelve en `duplicateConflicts`, identificado por la agencia de cuatro dígitos y el albarán, para su revisión manual.

`requestedRowCount` mantiene el número de filas `Solicitada` antes de deduplicar, `processableRowCount` cuenta las expediciones deduplicadas que pueden continuar y `duplicateConflictCount` cuenta grupos contradictorios. `rows` contiene exclusivamente las candidatas procesables.

## Alcance de RF-05

RF-05 se ejecuta después de RF-04 y solo transforma las candidatas deduplicadas que pueden continuar. La fila fuente inmutable se conserva para no perder los valores leídos del CSV. Cada candidata preparada expone:

- Agencia ajustada a cuatro dígitos con ceros a la izquierda.
- Código postal ajustado a cinco dígitos con ceros a la izquierda.
- Referencia original sin modificar.
- Referencia adicional normalizada para el futuro matching.

La referencia normalizada se convierte a minúsculas, elimina las tildes y convierte cualquier secuencia de signos o separadores en un único espacio. La `ñ` se conserva y no se convierte en `n`. Por ejemplo:

```text
PEÑA-MUÑOZ, Álvarez → peña muñoz alvarez
```

Por decisión de producto, los CSV no contendrán agencias o códigos postales vacíos, no numéricos o demasiado largos. RF-05 no introduce una clasificación de errores no solicitada para esos casos. La normalización tampoco modifica el albarán, la fecha, el estado ni construye todavía la URL de seguimiento; esa última responsabilidad pertenece a RF-06.

## Alcance de RF-06

RF-06 se ejecuta después de RF-05 y añade a cada candidata procesable una URL de seguimiento NACEX construida únicamente con su agencia normalizada y su albarán textual:

```text
https://www.nacex.es/seguimientoDetalle.do?agencia_origen={AGENCIA}&numero_albaran={ALBARAN}&estado=4&internacional=0&externo=N
```

La agencia ya llega ajustada a cuatro dígitos por RF-05. El albarán conserva exactamente su valor textual, incluidos los ceros iniciales; solo se codifican ambos valores al insertarlos en la query. La URL base y los parámetros constantes `estado=4`, `internacional=0` y `externo=N` están centralizados en `config/nacex.php`.

Esta fase no abre la URL, no consulta NACEX ni Shopify y no modifica ningún sistema externo. Los grupos contradictorios apartados por RF-04 no reciben URL porque siguen excluidos del procesamiento automático.

## Cabeceras obligatorias

Las cabeceras requeridas son:

```text
Ag. Origen
Albarán
Fecha alta
Referencia
CP Ent.
Estado
```

Se elimina un posible BOM de UTF-8 y se ignoran espacios exteriores de cada cabecera. Los nombres se comparan respetando mayúsculas, tildes y puntuación. Las columnas adicionales están permitidas y las cabeceras adicionales vacías se ignoran.

Si faltan columnas, el mensaje enumera exclusivamente sus nombres, sin mostrar datos del archivo.

## Contrato HTTP

### Petición

```text
POST /csv/validate
Content-Type: multipart/form-data
Campo: csv
```

La petición requiere el token CSRF normal de Laravel.

### Respuesta válida

```json
{
  "data": {
    "fileName": "expediciones.csv",
    "sizeInBytes": 2048,
    "rowCount": 25,
    "requestedRowCount": 17,
    "processableRowCount": 16,
    "duplicateConflictCount": 0,
    "encoding": "UTF-8",
    "headers": ["Ag. Origen", "Albarán", "Fecha alta", "Referencia", "CP Ent.", "Estado"],
    "rows": [
      {
        "originAgency": "0304",
        "trackingNumber": "0014910300",
        "registrationDate": "01/08/2026",
        "reference": "Peña Muñoz",
        "normalizedReference": "peña muñoz",
        "postalCode": "08019",
        "status": "Solicitada",
        "trackingUrl": "https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=0014910300&estado=4&internacional=0&externo=N"
      }
    ],
    "duplicateConflicts": []
  }
}
```

Un CSV estructuralmente válido sin filas `Solicitada` responde igualmente con código HTTP `200`, `requestedRowCount: 0`, `processableRowCount: 0` y las listas vacías. La interfaz informa que no se realizará ningún cambio en Shopify.

Un grupo contradictorio se excluye de `rows` y conserva sus filas originales únicamente en memoria:

```json
{
  "duplicateConflictCount": 1,
  "duplicateConflicts": [
    {
      "originAgency": "0304",
      "trackingNumber": "14910300",
      "rows": [
        { "originAgency": "304", "trackingNumber": "14910300", "registrationDate": "01/08/2026", "reference": "Ana García", "postalCode": "08019", "status": "Solicitada" },
        { "originAgency": "0304", "trackingNumber": "14910300", "registrationDate": "01/08/2026", "reference": "Ana García", "postalCode": "28019", "status": "SOLICITADA" }
      ]
    }
  ]
}
```

### Respuesta no válida

Código HTTP `422`, siguiendo el contrato de validación de Laravel:

```json
{
  "message": "El archivo CSV no es válido.",
  "errors": {
    "csv": ["El archivo no contiene todas las columnas necesarias. Faltan: Referencia, Estado."]
  }
}
```

## Responsabilidades

- `UploadNacexCsvRequest`: valida presencia, subida, extensión, tipo y tamaño.
- `ValidateNacexCsv`: coordina la lectura, el filtro de estado, la deduplicación, la normalización y la construcción de URLs.
- `DeduplicateNacexCsvRows`: agrupa las candidatas por identidad, conserva una repetición compatible y aparta los grupos contradictorios.
- `NormalizeNacexCsvRows`: transforma las filas deduplicadas en candidatas tipadas sin modificar sus DTO fuente.
- `BuildNacexTrackingUrls`: añade la URL de seguimiento a cada candidata normalizada sin alterar sus datos.
- `NacexCsvInspector`: contrato de aplicación para inspeccionar y leer el archivo sin conocer Laravel.
- `NacexCsvRow`: DTO inmutable que mantiene las seis columnas requeridas como cadenas.
- `NacexShipmentStatus`: regla de dominio que normaliza exclusivamente el estado para decidir si es `Solicitada`.
- `NacexShipmentIdentity`: regla de dominio que construye la identidad temporal mediante agencia ajustada y albarán sin modificar el DTO.
- `NacexShipmentNormalizer`: centraliza la agencia, el código postal y la referencia normalizados; la identidad RF-04 reutiliza su regla de agencia.
- `NacexTrackingUrlBuilder`: encapsula el formato exacto de la URL y codifica los valores de la query a partir de constantes configuradas.
- Implementación de infraestructura NACEX: detecta codificación, lee cabeceras y filas con el parser CSV nativo y adapta cada fila al DTO.
- Controlador HTTP: transforma errores del caso de uso en una respuesta `422` segura.
- Vue: mantiene el archivo y el resultado solo en memoria, presenta feedback y no aplica reglas críticas por sí solo.

## Datos y persistencia

- El archivo usa únicamente el temporal administrado por PHP durante la petición.
- No se copia a `storage/` ni a ningún almacenamiento permanente.
- La cookie de sesión cifrada se utiliza solo para CSRF y mensajes operativos.
- El CSV, sus filas y el resultado nunca se guardan en sesión, cookies, `localStorage` o `sessionStorage`.
- Las filas viajan en la respuesta JSON y permanecen únicamente dentro del resultado reactivo en memoria de Vue.
- El composable adapta de forma defensiva una respuesta RF-03 que todavía no incluya las métricas de RF-04: considera sus filas filtradas como procesables y asume cero conflictos. Esta compatibilidad evita que un build frontend nuevo deje la pantalla en blanco durante una actualización local desacompasada; el comportamiento completo de RF-04 requiere que backend y frontend estén desplegados juntos.
- El estado de Vue se pierde al recargar o cerrar la página.

## Interfaz

La pantalla incluye selector de archivo, área de arrastrar y soltar, botón `Analizar CSV`, columnas esperadas y aviso de ausencia de historial. Proporciona estados normal, arrastre, archivo seleccionado, validación, éxito, ausencia de candidatas y error.

El resultado muestra filas totales, solicitadas antes de deduplicar, procesables, grupos contradictorios, codificación y tamaño. Los conflictos se presentan mediante agencia, albarán y número de filas afectadas; sus datos completos permanecen en memoria para la futura revisión. El éxito confirma además que agencia, código postal y referencia de comparación ya están normalizados sin alterar la referencia original y que la URL de seguimiento está preparada para cada candidata procesable. Cuando no existen filas `Solicitada`, utiliza un aviso accesible y confirma que no se realizará ningún cambio en Shopify. La tabla general de análisis sigue perteneciendo a requisitos posteriores.

No se añade librería visual ni de movimiento. Se utilizan componentes Vue y CSS propios. Las únicas transiciones son feedback funcional breve de color y pulsación; deben respetar teclado, tacto y `prefers-reduced-motion`.

## Verificación ejecutada

- CSV UTF-8 válido.
- CSV Windows-1252 con caracteres españoles.
- Contenido ISO-8859-1 compatible con `ñ` y tildes.
- UTF-8 con BOM.
- Campos entrecomillados que contienen el separador `;`.
- Agencia, código postal y albarán con ceros iniciales.
- Albaranes largos y valores con notación científica literal sin conversión numérica.
- Estado `Solicitada` canónico, en mayúsculas, minúsculas y con espacios ASCII o Unicode sobrantes.
- Exclusión de estados diferentes como `En tránsito`, `Incidencia` y `Entregado`.
- Conservación del estado original en las filas candidatas.
- CSV válido sin candidatas, con respuesta vacía no errónea.
- Conteo separado de filas totales y candidatas antes de RF-04.
- Deduplicación de agencias equivalentes como `304` y `0304`, con espacios exteriores ASCII o Unicode.
- Conservación textual de la primera fila cuando las repeticiones son compatibles.
- Conservación diferenciada de albaranes o agencias realmente distintos.
- Exclusión completa y señalización de grupos que contradicen fecha, referencia o código postal.
- Agencia de dos, tres o cuatro dígitos ajustada a cuatro posiciones.
- Código postal de cuatro o cinco dígitos ajustado a cinco posiciones.
- Referencias en minúsculas, sin tildes y con los signos convertidos en espacios.
- Conservación de `ñ` y de la referencia fuente sin modificar.
- Construcción exacta de la URL NACEX con agencia de cuatro dígitos y albarán textual.
- Conservación de ceros iniciales y codificación segura de los valores de la query.
- Ausencia de URLs para grupos contradictorios excluidos por RF-04.
- Archivo ausente, extensión incorrecta o tamaño superior a 5 MB.
- Separador incorrecto.
- Archivo sin datos.
- Columnas obligatorias ausentes.
- Columnas adicionales y cabeceras adicionales vacías.
- Límite de 1.000 filas y rechazo de la fila 1.001.
- Respuesta accesible de éxito y error en la interfaz.

RF-01 se comprobó mediante pruebas unitarias y Feature, typecheck de Vue, build de producción y navegación real en XAMPP. La prueba visual cubrió escritorio, móvil a 390 × 844 sin desbordamiento horizontal, selección mediante el control nativo, estado de carga, respuesta válida y error por columnas ausentes.

Con RF-02, la suite completa alcanza 19 tests y 92 assertions. También pasan Pint, el typecheck de Vue, el build de producción, la validación estricta de Composer y las auditorías de Composer y npm sin vulnerabilidades conocidas. RF-02 conserva la interacción visual ya comprobada y únicamente amplía el texto del estado de éxito y el contrato de datos en memoria.

Con RF-03, la suite completa alcanza 30 tests y 115 assertions. También pasan Pint, el typecheck de Vue, el build de producción y la validación estricta de Composer.

La navegación real en XAMPP comprobó un CSV de cuatro filas con dos candidatas y otro de dos filas sin candidatas. Se verificaron el texto y las cuatro métricas de ambos estados, el aviso ámbar de resultado vacío, la ausencia de errores de consola y la vista móvil a 390 × 844 sin desbordamiento horizontal. La interfaz no añade animaciones nuevas.

Durante la verificación real se confirmó que Windows/PHP puede identificar un CSV textual como `text/plain`; por eso el transporte acepta ese MIME únicamente junto con extensión `.csv` y una inspección estructural posterior.

Con RF-04, la suite completa alcanza 38 tests y 161 assertions. También pasan Pint, el typecheck de Vue, el build de producción, la validación estricta de Composer y las auditorías de Composer y npm sin vulnerabilidades conocidas.

La verificación visual real utilizó un CSV sintético con cinco filas `Solicitada`: dos repeticiones compatibles de `304`/`0304`, un grupo de dos filas con código postal contradictorio y una expedición independiente. La interfaz mostró cinco solicitadas, dos procesables y un conflicto; identificó el grupo `0304 + 14910301`, lo dejó fuera del procesamiento automático y no produjo errores de consola. En escritorio y a 390 × 844 no hubo desbordamiento horizontal. RF-04 no añade animaciones.

También se reprodujo en XAMPP una actualización desacompasada donde el build RF-04 recibía una respuesta RF-03. La lectura directa de una métrica ausente provocaba un error de renderizado. El adaptador defensivo mantiene visible el resultado antiguo sin inventar conflictos y una respuesta sin los campos mínimos muestra un error operativo en vez de desmontar la interfaz.

Con RF-05, la suite completa alcanza 49 tests y 186 assertions. También pasan Pint, el typecheck de Vue, el build de producción, la validación estricta de Composer y las auditorías de Composer y npm sin vulnerabilidades conocidas. Las pruebas cubren agencia y código postal con y sin cero inicial, eliminación de tildes, conservación de `ñ`, conversión de guiones, apóstrofes y otros signos en espacios, conservación de la fila fuente y aplicación de la normalización únicamente después de deduplicar.

La verificación real utilizó una instancia local aislada y un CSV sintético con agencia `304`, código postal `8019` y referencia `PEÑA-MUÑOZ, Álvarez`. La respuesta mantuvo una candidata procesable y la interfaz confirmó la normalización sin alterar la referencia original. El estado fue legible en escritorio y a 390 × 844, sin desbordamiento horizontal, y una carga limpia del build definitivo no produjo errores ni avisos de consola. RF-05 no incorpora animaciones nuevas.

Con RF-06, la suite completa alcanza 52 tests y 193 assertions. También pasan Pint, el typecheck de Vue, el build de producción, la validación estricta de Composer y las auditorías de Composer y npm sin vulnerabilidades conocidas. Las pruebas cubren el formato exacto de la URL, el uso de la agencia normalizada, la conservación del albarán y sus ceros iniciales, la codificación de valores de query y la incorporación de la URL sin alterar la candidata RF-05.

La verificación real de RF-06 reutilizó una instancia local aislada y un CSV sintético con agencia `304` y albarán `0014910300`. La interfaz mostró una candidata procesable y confirmó que su URL de seguimiento estaba preparada. A 390 × 844, el ancho del documento permaneció dentro del viewport y no hubo mensajes de consola. Después se cerró la instancia temporal y se restauró el build local normal. RF-06 no incorpora controles ni animaciones nuevas.
