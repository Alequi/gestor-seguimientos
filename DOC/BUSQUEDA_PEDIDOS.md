# Búsqueda de pedidos en Shopify

## Estado

RF-07 a RF-11 están implementados hasta la selección temporal. El endpoint de carga CSV consulta Shopify en modo de solo lectura, compara nombre de envío, código postal y fecha, y devuelve una clasificación segura por expedición. La ejecución posterior ya está documentada en `CUMPLIMIENTOS.md`. La única comprobación realizada contra la tienda real sigue siendo una consulta mínima de conexión; durante estos cambios no se consultaron pedidos reales ni se modificó Shopify.

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

La regla está aislada en dominio y participa en el endpoint CSV a través de los casos de uso de búsqueda y análisis. `registrationDate` se conserva además como texto en la respuesta.

## Lectura paginada preparada para RF-07

`FindShopifyOrdersForNacexDate` obtiene la ventana de búsqueda y delega en el contrato `ShopifyOrderReader`. Su adaptador Admin GraphQL consulta pedidos por `created_at`, convierte ambos límites a UTC y recorre todas las páginas mediante cursor. Aplica además el intervalo localmente para rechazar cualquier pedido fuera de los límites aunque aparezca en la respuesta. Una respuesta incompleta, un cursor inválido o un pedido duplicado producen un error seguro; nunca se devuelve una lista parcial como si fuera completa.

Se recuperan el identificador y nombre del pedido, fecha de creación y cancelación, estado agregado de preparación, nombre y código postal de envío, cumplimientos, seguimientos y hasta las dos primeras órdenes de cumplimiento. Dos órdenes bastan para bloquear el caso como múltiple. Si Shopify indica más páginas de órdenes de cumplimiento, también se considera múltiple. La lectura compara `fulfillmentsCount` con los cumplimientos recibidos y falla de forma segura si la respuesta queda truncada o incompleta. Si el nombre completo de envío está vacío, se compone a partir de nombre y apellidos de esa misma dirección, cuando ambos existen.

La consulta se validó contra el esquema Admin GraphQL `2026-10`. Shopify informó de que requiere `read_orders` y los scopes de órdenes de cumplimiento que correspondan al modelo de la tienda (`read_assigned_fulfillment_orders`, `read_merchant_managed_fulfillment_orders` o `read_third_party_fulfillment_orders`, además de equivalentes especiales solo si fueran aplicables). Estos permisos no se comprobaron contra la tienda real. La consulta no añade el campo `customer`, que requeriría un permiso adicional aún no confirmado. El tamaño de página de pedidos es 100; no se aplica un límite silencioso de páginas.

## Comparación de nombre y código postal preparada para RF-08

`RecipientNameCompatibility` reutiliza la normalización de RF-05 para ambos nombres. Acepta la dirección habitual, en la que el nombre Shopify empieza por la referencia NACEX. Para la dirección inversa, admite solo uno o dos caracteres Unicode adicionales al final de la referencia normalizada y rechaza un sufijo con espacios. No utiliza similitud difusa. El límite se centraliza en `config/nacex.php`.

`FindShippingAddressMatchesForShipment` exige además código postal numérico de hasta cinco dígitos, normalizado como texto, y vuelve a comprobar la fecha. Mantiene pedidos preparados para que la clasificación pueda explicar y bloquear esos casos. Si falta nombre o código postal de envío, no crea una coincidencia automática. El respaldo por nombre de cliente o de facturación y la revisión manual quedan para fases posteriores; no se ha supuesto que el permiso `read_customers` esté disponible.

El endpoint existente de carga y validación CSV invoca ya este lector después del filtrado, deduplicación, normalización y construcción del seguimiento. Los resultados se devuelven en la respuesta JSON y la interfaz RF-10 los presenta sin añadir reglas de negocio en el navegador. Las pruebas usan respuestas simuladas y datos ficticios.

## Clasificación segura de RF-09

Cada expedición se procesa de forma independiente. Los pedidos cancelados se excluyen antes de decidir la coincidencia. Sin candidatos se devuelve `NO_MATCH`; con dos o más se devuelve `AMBIGUOUS` y se conservan los candidatos únicamente para revisión.

Cuando existe un solo candidato se aplica este orden:

1. Si el mismo albarán ya aparece en cualquier seguimiento, `TRACKING_EXISTS`.
2. Si el pedido está completamente preparado, `ALREADY_FULFILLED`.
3. Si está parcialmente preparado o Shopify devuelve varias órdenes de cumplimiento, `PARTIAL_FULFILLMENT`.
4. Si existe un cumplimiento pero el seguimiento no permite concluir que sea el mismo, `FULFILLMENT_EXISTS` y revisión manual.
5. Solo un pedido `UNFULFILLED`, sin cumplimientos y con una única orden `OPEN` obtiene `MATCHED_SAFE`.
6. Cualquier combinación no concluyente devuelve `ERROR` con un mensaje seguro y no procesable.

Así, `TRACKING_EXISTS` prevalece expresamente sobre `ALREADY_FULFILLED` cuando ambos son aplicables. La ambigüedad se resuelve antes de inspeccionar el estado de un pedido concreto: nunca se selecciona silenciosamente uno entre varios. Un fallo de fecha o de comunicación con Shopify se limita a esa expedición y el resto del lote continúa. Los detalles técnicos de Shopify no se exponen en la respuesta.

Las expediciones con la misma `Fecha alta` reutilizan dentro de la petición la misma lectura de solo lectura. No existe caché persistente ni se guardan resultados entre cargas o sesiones.

## Acceso a Shopify preparado

`config/shopify.php` centraliza la versión `2026-10` y lee `SHOPIFY_SHOP`, `SHOPIFY_CLIENT_ID` y `SHOPIFY_CLIENT_SECRET` del entorno. `SHOPIFY_SHOP` es únicamente el subdominio de la tienda, sin `.myshopify.com`; se valida antes de construir URLs HTTPS para evitar destinos arbitrarios. Los tres valores siguen vacíos en `.env.example`; el usuario los configuró en su `.env` local y se comprobó solo su presencia, sin mostrar ni copiar valores.

En el XAMPP actual, el `.htaccess` de la raíz impide acceder por HTTP a `.env` y al resto del proyecto fuera de `public/`. La comprobación de solo cabeceras devolvió `403` para `.env` y `200` para `public/`; el contenido de `.env` no se leyó. En otro hosting se debe publicar únicamente `public/` y verificar de nuevo la protección antes de cargar credenciales.

`ShopifyClientCredentialsTokenProvider` envía el ID y el secreto al endpoint OAuth de la tienda mediante `grant_type=client_credentials`. Comprueba la respuesta y conserva el token solo en memoria hasta poco antes de su caducidad; no usa sesión, cookies, base de datos, caché persistente ni logs. `ShopifyAdminGraphqlClient` adjunta el token en `X-Shopify-Access-Token` al endpoint versionado y rechaza respuestas HTTP o GraphQL incompletas o erróneas con mensajes seguros, sin propagar el cuerpo de respuesta. Cuando una operación no requiere variables, omite `variables` del JSON: enviar `[]` causó el error real `Invalid variables parameter.` aunque la autenticación funcionaba. Las variables presentes deben tener nombres y se envían como objeto JSON. No hay reintentos automáticos ni operaciones GraphQL de negocio conectadas todavía.

Shopify documenta este flujo para aplicaciones y tiendas de la misma organización, con tokens de unas 24 horas. Referencias: [credenciales de cliente](https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant?lang=node) y [Admin GraphQL API `2026-10`](https://shopify.dev/docs/api/admin-graphql/2026-10).

## Vista previa de RF-10

La tabla compacta muestra los datos NACEX, el enlace de seguimiento, el pedido único o los candidatos ambiguos, el destinatario, el estado Shopify y la clasificación segura. La cabecera permanece fija dentro de una región con desplazamiento; en móvil se conserva la tabla completa mediante desplazamiento horizontal. No se anima cada fila ni se introduce persistencia.

Los candidatos ambiguos se muestran únicamente para revisión y nunca se selecciona uno de forma automática. Esta vista no incluye casillas, asignación manual, enlaces al administrador de Shopify ni botones de ejecución. El frontend traduce estados a etiquetas legibles, pero no recalcula matching, precedencias ni seguridad: consume la decisión del backend y rechaza contratos desconocidos.

## Selección temporal de RF-11

Solo `MATCHED_SAFE` se selecciona por defecto. Las filas ambiguas muestran radios para asignar uno de los candidatos devueltos; la asignación no marca la fila y cualquier cambio de candidato la desmarca. Estados ya preparados, parciales, con seguimiento o cumplimiento, sin coincidencia o con error permanecen bloqueados para selección. Todas las filas pueden excluirse y restaurarse durante la sesión.

El backend añade a cada pedido una URL administrativa construida exclusivamente desde el subdominio validado de la tienda y un GID con forma exacta `gid://shopify/Order/{id numérico}`. La URL no contiene credenciales. El frontend vuelve a comprobar protocolo, host, ruta, ausencia de query y fragmento antes de presentarla.

La selección, exclusión y asignación manual no se persisten. RF-11 por sí solo no contiene mutaciones; cuando el usuario confirma, RF-12 a RF-18 envían la selección mínima junto con el mismo CSV al endpoint descrito en `CUMPLIMIENTOS.md`.

## Pendiente relacionado

- Completar la prioridad de nombres con cliente y facturación solo cuando se confirme y verifique el permiso necesario; la dirección de envío continúa siendo la única fuente automática actual.
- Confirmar en un entorno de desarrollo los scopes de lectura de pedidos y órdenes de cumplimiento antes de utilizar el análisis con pedidos de prueba.
- RF-19 añadirá el informe descargable de la sesión; la confirmación, revalidación, mutación y resultados por fila ya están implementados.

## Verificación

Las pruebas unitarias de la regla temporal cubren ambos días límite, los días inmediatamente exteriores, la conversión horaria a Madrid, una ventana configurada diferente y el rechazo de una fecha imposible. No utilizan filas ni datos personales del CSV real. La suite completa pasa con 58 tests y 200 assertions; también pasan Pint y `git diff --check`.

Las pruebas automatizadas del cliente Shopify utilizan exclusivamente respuestas HTTP simuladas: comprueban URLs, intercambio de credenciales, encabezados, reutilización y renovación del token, configuración ausente, omisión de variables vacías, envío de variables nombradas y errores sin exposición de secretos. La suite completa pasa con 72 tests y 223 assertions; también pasan Pint y `git diff --check`.

Las pruebas añadidas para el lector cubren límites UTC derivados de `Europe/Madrid`, cambio horario, paginación, conservación de pedidos preparados para comprobaciones posteriores, filtrado defensivo del instante final y rechazo de cursores inválidos o repetidos. No acceden a la tienda real.

Las pruebas de RF-08 cubren prefijo habitual, límite inverso de uno y dos caracteres, rechazo de tres caracteres o una palabra adicional, tildes, `ñ`, nombre vacío, código postal distinto y fecha exterior. Las pruebas de RF-09 cubren ausencia y ambigüedad, exclusión de cancelados, precedencia del tracking, pedido preparado, parcial, varias órdenes de cumplimiento, cumplimiento inconcluyente, estado no procesable, rechazo de cumplimientos incompletos, aislamiento de errores, reutilización de lecturas por fecha y exposición de la clasificación desde el endpoint CSV. RF-10 y RF-11 se verificaron mediante el contrato TypeScript y el build de producción; las pruebas backend de RF-11 cubren construcción y rechazo seguro de enlaces administrativos y su exposición en la respuesta. La verificación de RF-12 a RF-18 se detalla en `CUMPLIMIENTOS.md`. No se consultaron pedidos reales ni se modificó Shopify; toda la información de prueba es ficticia.

La comprobación manual de conexión utilizó únicamente `shop { id }` y no imprimió el identificador. Laravel cargó los tres valores de configuración; el primer intento autenticó correctamente, pero Shopify devolvió `Invalid variables parameter.` por recibir `variables: []`. Tras omitir ese campo cuando está vacío, la misma consulta de solo lectura devolvió una respuesta válida. Ambas operaciones de prueba se validaron contra el esquema GraphQL `2026-10`. No se consultaron pedidos, no se modificó Shopify y no se abrió un navegador. Después de guardar las credenciales, `.env` siguió respondiendo `403` por HTTP y `public/` respondió `200`.
