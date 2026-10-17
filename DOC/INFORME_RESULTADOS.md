# Informe CSV de resultados

## Estado

RF-19 está implementado. Después de recibir resultados de ejecución, el usuario puede descargar un CSV que reúne todas las expediciones analizadas durante la carga actual. El informe se genera y devuelve durante esa petición; la aplicación no conserva una copia ni permite recuperar sesiones anteriores.

## Alcance confirmado

El informe incluye todas las expediciones analizadas, también las que no se seleccionaron o no llegaron a ejecutarse. Se aplican las 16 columnas definidas por el PRD:

1. `Referencia NACEX`
2. `Agencia`
3. `Albarán`
4. `Código postal`
5. `Fecha alta`
6. `Estado NACEX`
7. `URL de seguimiento`
8. `Resultado del matching`
9. `Pedido Shopify`
10. `Cliente Shopify`
11. `Fecha pedido`
12. `Estado previo del pedido`
13. `Resultado del cumplimiento`
14. `Notificación solicitada`
15. `Fecha de procesamiento`
16. `Mensaje de error`

La decisión de producto resuelve la diferencia de terminología entre RF-19 y el PRD: la aplicación informa si pidió a Shopify que notificara, porque no puede confirmar que el correo haya sido entregado. El valor es `SÍ` únicamente cuando el cumplimiento termina como `COMPLETED` y esa ejecución llevaba activa la opción de notificar; en cualquier otro caso es `NO`.

Las filas no ejecutadas conservan vacío `Resultado del cumplimiento` y `Fecha de procesamiento`. Esta fecha se registra cuando el navegador recibe el resultado, con formato `DD/MM/AAAA HH:mm:ss` y zona `Europe/Madrid`. `Mensaje de error` contiene el error seguro de ejecución cuando el estado es `ERROR`; en filas no ejecutadas conserva, si existe, el error seguro del análisis.

Los campos singulares de Shopify usan el pedido identificado de forma segura. En una coincidencia ambigua no asignada permanecen vacíos; si el usuario asignó y ejecutó manualmente un candidato, el informe utiliza ese pedido. No se añaden GID, credenciales, tokens, detalles internos de GraphQL ni datos que no estuvieran ya presentes en la sesión de Vue.

## Formato y seguridad

- Separador `;` y finales de línea CRLF.
- UTF-8 con BOM para facilitar la apertura en hojas de cálculo y conservar tildes y `ñ`.
- Albarán, agencia y código postal se escriben como texto y conservan ceros iniciales.
- Los campos que empiezan por `=`, `+`, `-` o `@`, ignorando espacios iniciales para la detección, reciben un apóstrofo inicial para impedir que una hoja de cálculo los interprete como fórmulas.
- Las comillas, el separador y los saltos de línea se escapan mediante las reglas estándar de CSV.
- El nombre descargado es `informe-ejecucion-nacex.csv`.

## Flujo y arquitectura

`FulfillmentExecutionPanel.vue` conserva en memoria el resultado de cada intento, la opción de notificación aplicable y su fecha de procesamiento. Al descargar, cruza cada identidad `agencia + albarán` con las filas de análisis y envía exclusivamente las 16 columnas finales a `POST /reports/execution`.

`DownloadExecutionReportRequest` limita la petición a 1.000 filas y valida el contrato textual. `ExecutionReportController` adapta cada fila a `ExecutionReportRow`; `GenerateExecutionReportCsv` crea el contenido en un flujo temporal y lo devuelve con `Content-Type: text/csv`, descarga adjunta y `Cache-Control: no-store`. No escribe archivos, no usa base de datos y no guarda el contenido en sesión o cookie.

Un error de descarga mantiene intactos los resultados en pantalla y permite volver a intentarlo. La generación del informe no consulta ni modifica Shopify.

## Verificación

Las pruebas automatizadas cubren las 16 cabeceras, UTF-8 con BOM, separador, conservación de ceros iniciales, caracteres españoles, campos con punto y coma, neutralización de fórmulas, cabeceras HTTP sin caché y rechazo de un informe vacío. La verificación del cambio incluye formato PHP, suite PHP completa, comprobación TypeScript y build de producción.

El usuario confirmó además la revisión visual del flujo y de la descarga en escritorio y móvil.
