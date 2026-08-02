# Importación de CSV NACEX

## Estado

RF-01, RF-02 y RF-03 implementados y verificados. La aplicación permite cargar, validar estructuralmente, leer un CSV como texto y conservar como candidatas únicamente las filas `Solicitada`, sin almacenarlo ni consultar Shopify.

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

RF-02 no recorta ni normaliza los valores originales y no valida el formato individual de cada campo. El valor leído se conserva para que las transformaciones posteriores sean explícitas y comprobables.

## Alcance de RF-03

El caso de uso filtra las filas leídas y devuelve como candidatas únicamente aquellas cuyo estado normalizado equivale a `Solicitada`.

Para decidir la equivalencia del estado se ignoran:

- Mayúsculas y minúsculas.
- Espacios al principio y al final.
- Espacios duplicados, tabulaciones y separadores Unicode equivalentes.

El estado original de una fila candidata se mantiene sin modificar en su DTO; la representación normalizada solo se utiliza dentro de la regla de dominio. Los demás estados se excluyen de `rows` y no avanzan hacia RF-04 ni requisitos posteriores.

`rowCount` conserva el total de filas no vacías del archivo y `requestedRowCount` informa cuántas filas `Solicitada` quedan antes de deduplicar. Por tanto, `rows` contiene exactamente `requestedRowCount` elementos. RF-03 no deduplica, no normaliza agencia o código postal y no construye URLs.

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
    "encoding": "UTF-8",
    "headers": ["Ag. Origen", "Albarán", "Fecha alta", "Referencia", "CP Ent.", "Estado"],
    "rows": [
      {
        "originAgency": "0304",
        "trackingNumber": "0014910300",
        "registrationDate": "01/08/2026",
        "reference": "Peña Muñoz",
        "postalCode": "08019",
        "status": "Solicitada"
      }
    ]
  }
}
```

Un CSV estructuralmente válido sin filas `Solicitada` responde igualmente con código HTTP `200`, `requestedRowCount: 0` y `rows: []`. La interfaz informa que no se realizará ningún cambio en Shopify.

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
- `ValidateNacexCsv`: coordina la lectura y conserva únicamente las filas candidatas.
- `NacexCsvInspector`: contrato de aplicación para inspeccionar y leer el archivo sin conocer Laravel.
- `NacexCsvRow`: DTO inmutable que mantiene las seis columnas requeridas como cadenas.
- `NacexShipmentStatus`: regla de dominio que normaliza exclusivamente el estado para decidir si es `Solicitada`.
- Implementación de infraestructura NACEX: detecta codificación, lee cabeceras y filas con el parser CSV nativo y adapta cada fila al DTO.
- Controlador HTTP: transforma errores del caso de uso en una respuesta `422` segura.
- Vue: mantiene el archivo y el resultado solo en memoria, presenta feedback y no aplica reglas críticas por sí solo.

## Datos y persistencia

- El archivo usa únicamente el temporal administrado por PHP durante la petición.
- No se copia a `storage/` ni a ningún almacenamiento permanente.
- La cookie de sesión cifrada se utiliza solo para CSRF y mensajes operativos.
- El CSV, sus filas y el resultado nunca se guardan en sesión, cookies, `localStorage` o `sessionStorage`.
- Las filas viajan en la respuesta JSON y permanecen únicamente dentro del resultado reactivo en memoria de Vue.
- El estado de Vue se pierde al recargar o cerrar la página.

## Interfaz

La pantalla incluye selector de archivo, área de arrastrar y soltar, botón `Analizar CSV`, columnas esperadas y aviso de ausencia de historial. Proporciona estados normal, arrastre, archivo seleccionado, validación, éxito, ausencia de candidatas y error.

El resultado muestra filas totales y candidatas antes de deduplicar, además de codificación y tamaño. Cuando no existen filas `Solicitada`, utiliza un aviso accesible con el mensaje aprobado y confirma que no se realizará ningún cambio en Shopify. Las filas todavía no se muestran porque la tabla de análisis corresponde a requisitos posteriores.

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
