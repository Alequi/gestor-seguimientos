# Búsqueda de pedidos en Shopify

## Estado

RF-07 y RF-08 están en desarrollo. La compatibilidad temporal, un lector paginado de pedidos y la comparación conservadora con el nombre de envío están implementados. Todavía no están conectados al flujo CSV ni clasifican coincidencias como seguras para preparar. La única comprobación contra la tienda real sigue siendo una consulta mínima de conexión; no se han consultado pedidos reales ni modificado Shopify.

## Decisiones confirmadas

- La integración utilizará Shopify Admin GraphQL API `2026-10` para una única tienda.
- En el CSV NACEX proporcionado para definir el formato, las 40 filas de `Fecha alta` siguen `DD/MM/AAAA`, sin hora. El archivo real no se incorpora al repositorio ni a las pruebas.
- La fecha de creación del pedido se convierte a día de calendario en `Europe/Madrid`.
- Un pedido tiene fecha compatible si su día local está entre 30 días antes y 7 días después de `Fecha alta`, incluidos ambos límites.
- La ventana y la zona horaria se centralizan en `config/nacex.php` para permitir su configuración.
- El usuario confirmó que su aplicación existe en Shopify Dev Dashboard, está instalada en la tienda de su misma organización y dispone de ID y secreto de cliente. Se utilizará el flujo de credenciales de cliente desde el backend.
- Por decisión delegada expresamente por el usuario, la coincidencia inversa de RF-08 admitirá como máximo **dos caracteres Unicode adicionales** al final de la referencia NACEX normalizada respecto al nombre Shopify normalizado. El sufijo no puede contener un espacio: no se admitirá una palabra nueva. Esta regla no sustituye la igualdad de código postal, la compatibilidad temporal ni la exigencia de un único candidato; cualquier duda permanece en revisión.

## Regla temporal implementada

`OrderDateCompatibility` interpreta estrictamente `Fecha alta` como `DD/MM/AAAA`. Rechaza fechas imposibles o con otra forma en lugar de convertirlas silenciosamente. Calcula un intervalo de instantes con inicio incluido y fin excluido, equivalente a incluir completos los días límite en `Europe/Madrid`, incluso cuando cambia el horario de verano. La comparación de un pedido reutiliza ese mismo intervalo.

La regla está aislada en dominio y aún no participa en el endpoint de validación de CSV. Ese endpoint conserva `registrationDate` como texto y mantiene su respuesta anterior.

## Lectura paginada preparada para RF-07

`FindShopifyOrdersForNacexDate` obtiene la ventana de búsqueda y delega en el contrato `ShopifyOrderReader`. Su adaptador Admin GraphQL consulta pedidos por `created_at`, convierte ambos límites a UTC y recorre todas las páginas mediante cursor. Aplica además el intervalo localmente para rechazar cualquier pedido fuera de los límites aunque aparezca en la respuesta. Una respuesta incompleta, un cursor inválido o un pedido duplicado producen un error seguro; nunca se devuelve una lista parcial como si fuera completa.

Por ahora se recuperan los campos mínimos para la fase siguiente: identificador y nombre del pedido, fecha de creación y cancelación, estado de preparación, nombre y código postal de envío. Si el nombre completo de envío está vacío, se compone a partir de nombre y apellidos de esa misma dirección, cuando ambos existen. La lectura conserva también pedidos cancelados o ya preparados: los requisitos posteriores deben distinguirlos y detectar duplicados antes de excluirlos. No se decide aquí una coincidencia por estado ni se permite preparar pedidos. La consulta ampliada se validó contra el esquema Admin GraphQL `2026-10` de Shopify y no añade el campo `customer`, que necesitaría un permiso adicional aún no comprobado. El tamaño de página es 100; no se aplica un límite silencioso de páginas.

## Comparación de nombre y código postal preparada para RF-08

`RecipientNameCompatibility` reutiliza la normalización de RF-05 para ambos nombres. Acepta la dirección habitual, en la que el nombre Shopify empieza por la referencia NACEX. Para la dirección inversa, admite solo uno o dos caracteres Unicode adicionales al final de la referencia normalizada y rechaza un sufijo con espacios. No utiliza similitud difusa. El límite se centraliza en `config/nacex.php`.

`FindShippingAddressMatchesForShipment` exige además código postal numérico de hasta cinco dígitos, normalizado como texto, y vuelve a comprobar la fecha. Devuelve los pedidos cuyo destinatario de envío coincide, pero **no los califica aún como `MATCHED_SAFE`**: mantiene pedidos cancelados o preparados para que la clasificación posterior pueda explicar y bloquear esos casos. Si falta nombre o código postal de envío, no crea una coincidencia automática. El respaldo por nombre de cliente o de facturación y la revisión manual quedan para las fases siguientes; no se ha supuesto que el permiso `read_customers` esté disponible.

No hay ruta ni acción de interfaz que invoque todavía este lector. Tampoco se ha confirmado en la tienda real el permiso de lectura de pedidos. Las pruebas de esta pieza usan respuestas simuladas y datos ficticios.

## Acceso a Shopify preparado

`config/shopify.php` centraliza la versión `2026-10` y lee `SHOPIFY_SHOP`, `SHOPIFY_CLIENT_ID` y `SHOPIFY_CLIENT_SECRET` del entorno. `SHOPIFY_SHOP` es únicamente el subdominio de la tienda, sin `.myshopify.com`; se valida antes de construir URLs HTTPS para evitar destinos arbitrarios. Los tres valores siguen vacíos en `.env.example`; el usuario los configuró en su `.env` local y se comprobó solo su presencia, sin mostrar ni copiar valores.

En el XAMPP actual, el `.htaccess` de la raíz impide acceder por HTTP a `.env` y al resto del proyecto fuera de `public/`. La comprobación de solo cabeceras devolvió `403` para `.env` y `200` para `public/`; el contenido de `.env` no se leyó. En otro hosting se debe publicar únicamente `public/` y verificar de nuevo la protección antes de cargar credenciales.

`ShopifyClientCredentialsTokenProvider` envía el ID y el secreto al endpoint OAuth de la tienda mediante `grant_type=client_credentials`. Comprueba la respuesta y conserva el token solo en memoria hasta poco antes de su caducidad; no usa sesión, cookies, base de datos, caché persistente ni logs. `ShopifyAdminGraphqlClient` adjunta el token en `X-Shopify-Access-Token` al endpoint versionado y rechaza respuestas HTTP o GraphQL incompletas o erróneas con mensajes seguros, sin propagar el cuerpo de respuesta. Cuando una operación no requiere variables, omite `variables` del JSON: enviar `[]` causó el error real `Invalid variables parameter.` aunque la autenticación funcionaba. Las variables presentes deben tener nombres y se envían como objeto JSON. No hay reintentos automáticos ni operaciones GraphQL de negocio conectadas todavía.

Shopify documenta este flujo para aplicaciones y tiendas de la misma organización, con tokens de unas 24 horas. Referencias: [credenciales de cliente](https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant?lang=node) y [Admin GraphQL API `2026-10`](https://shopify.dev/docs/api/admin-graphql/2026-10).

## Pendiente para completar RF-07

- Conectar la lectura al análisis del CSV, sin introducir persistencia.
- Aplicar la prioridad completa de nombres documentada en la PRD, comprobar el permiso necesario para consultar clientes y clasificar los pedidos cancelados, preparados, parciales y los seguimientos existentes antes de permitir una selección segura.
- Conectar el resultado con el análisis y la revisión previstos para los requisitos siguientes, manteniendo los casos dudosos fuera de la automatización.

## Verificación

Las pruebas unitarias de la regla temporal cubren ambos días límite, los días inmediatamente exteriores, la conversión horaria a Madrid, una ventana configurada diferente y el rechazo de una fecha imposible. No utilizan filas ni datos personales del CSV real. La suite completa pasa con 58 tests y 200 assertions; también pasan Pint y `git diff --check`.

Las pruebas automatizadas del cliente Shopify utilizan exclusivamente respuestas HTTP simuladas: comprueban URLs, intercambio de credenciales, encabezados, reutilización y renovación del token, configuración ausente, omisión de variables vacías, envío de variables nombradas y errores sin exposición de secretos. La suite completa pasa con 72 tests y 223 assertions; también pasan Pint y `git diff --check`.

Las pruebas añadidas para el lector cubren límites UTC derivados de `Europe/Madrid`, cambio horario, paginación, conservación de pedidos preparados para comprobaciones posteriores, filtrado defensivo del instante final y rechazo de cursores inválidos o repetidos. No acceden a la tienda real.

Las nuevas pruebas de RF-08 cubren prefijo habitual, límite inverso de uno y dos caracteres, rechazo de tres caracteres o una palabra adicional, tildes, `ñ`, nombre vacío, código postal distinto, fecha exterior y conservación del estado Shopify para la clasificación posterior. Toda la información de prueba es ficticia. La verificación actual pasa con 88 tests y 254 assertions, Pint, typecheck, build y `git diff --check`.

La comprobación manual de conexión utilizó únicamente `shop { id }` y no imprimió el identificador. Laravel cargó los tres valores de configuración; el primer intento autenticó correctamente, pero Shopify devolvió `Invalid variables parameter.` por recibir `variables: []`. Tras omitir ese campo cuando está vacío, la misma consulta de solo lectura devolvió una respuesta válida. Ambas operaciones de prueba se validaron contra el esquema GraphQL `2026-10`. No se consultaron pedidos, no se modificó Shopify y no se abrió un navegador. Después de guardar las credenciales, `.env` siguió respondiendo `403` por HTTP y `public/` respondió `200`.
