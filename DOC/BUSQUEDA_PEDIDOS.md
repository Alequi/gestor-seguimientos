# Búsqueda de pedidos en Shopify

## Estado

RF-07 está en desarrollo. La compatibilidad temporal ya está implementada y probada como regla de dominio. El cliente de infraestructura puede obtener un token y enviar operaciones a Admin GraphQL, pero todavía no hay búsqueda de pedidos conectada al flujo CSV ni clasificación de resultados. Solo se ha utilizado una consulta de lectura mínima para comprobar la conexión; no se han consultado pedidos ni modificado Shopify.

## Decisiones confirmadas

- La integración utilizará Shopify Admin GraphQL API `2026-10` para una única tienda.
- En el CSV NACEX proporcionado para definir el formato, las 40 filas de `Fecha alta` siguen `DD/MM/AAAA`, sin hora. El archivo real no se incorpora al repositorio ni a las pruebas.
- La fecha de creación del pedido se convierte a día de calendario en `Europe/Madrid`.
- Un pedido tiene fecha compatible si su día local está entre 30 días antes y 7 días después de `Fecha alta`, incluidos ambos límites.
- La ventana y la zona horaria se centralizan en `config/nacex.php` para permitir su configuración.
- El usuario confirmó que su aplicación existe en Shopify Dev Dashboard, está instalada en la tienda de su misma organización y dispone de ID y secreto de cliente. Se utilizará el flujo de credenciales de cliente desde el backend.

## Regla temporal implementada

`OrderDateCompatibility` interpreta estrictamente `Fecha alta` como `DD/MM/AAAA`. Rechaza fechas imposibles o con otra forma en lugar de convertirlas silenciosamente. Recibe la fecha de creación del pedido como instante con zona horaria, la convierte a `Europe/Madrid` y compara solo los días de calendario. El intervalo inclusivo se calcula a partir de la fecha NACEX.

La regla está aislada en dominio y aún no participa en el endpoint de validación de CSV. Ese endpoint conserva `registrationDate` como texto y mantiene su respuesta anterior. Tampoco se realiza ninguna llamada a Shopify desde esta pieza.

## Acceso a Shopify preparado

`config/shopify.php` centraliza la versión `2026-10` y lee `SHOPIFY_SHOP`, `SHOPIFY_CLIENT_ID` y `SHOPIFY_CLIENT_SECRET` del entorno. `SHOPIFY_SHOP` es únicamente el subdominio de la tienda, sin `.myshopify.com`; se valida antes de construir URLs HTTPS para evitar destinos arbitrarios. Los tres valores siguen vacíos en `.env.example`; el usuario los configuró en su `.env` local y se comprobó solo su presencia, sin mostrar ni copiar valores.

En el XAMPP actual, el `.htaccess` de la raíz impide acceder por HTTP a `.env` y al resto del proyecto fuera de `public/`. La comprobación de solo cabeceras devolvió `403` para `.env` y `200` para `public/`; el contenido de `.env` no se leyó. En otro hosting se debe publicar únicamente `public/` y verificar de nuevo la protección antes de cargar credenciales.

`ShopifyClientCredentialsTokenProvider` envía el ID y el secreto al endpoint OAuth de la tienda mediante `grant_type=client_credentials`. Comprueba la respuesta y conserva el token solo en memoria hasta poco antes de su caducidad; no usa sesión, cookies, base de datos, caché persistente ni logs. `ShopifyAdminGraphqlClient` adjunta el token en `X-Shopify-Access-Token` al endpoint versionado y rechaza respuestas HTTP o GraphQL incompletas o erróneas con mensajes seguros, sin propagar el cuerpo de respuesta. Cuando una operación no requiere variables, omite `variables` del JSON: enviar `[]` causó el error real `Invalid variables parameter.` aunque la autenticación funcionaba. Las variables presentes deben tener nombres y se envían como objeto JSON. No hay reintentos automáticos ni operaciones GraphQL de negocio conectadas todavía.

Shopify documenta este flujo para aplicaciones y tiendas de la misma organización, con tokens de unas 24 horas. Referencias: [credenciales de cliente](https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant?lang=node) y [Admin GraphQL API `2026-10`](https://shopify.dev/docs/api/admin-graphql/2026-10).

## Pendiente para completar RF-07

- Consultar pedidos candidatos y adaptar los datos necesarios mediante un contrato de aplicación y una implementación de infraestructura Shopify.
- Aplicar las condiciones de pedido no cancelado, productos pendientes, nombre de destinatario y código postal, además de la regla temporal.
- Conectar el resultado con el análisis y la revisión previstos para los requisitos siguientes, manteniendo los casos dudosos fuera de la automatización.
- Resolver la diferencia máxima de longitud para la coincidencia inversa antes de automatizar ese tipo de matching.

## Verificación

Las pruebas unitarias de la regla temporal cubren ambos días límite, los días inmediatamente exteriores, la conversión horaria a Madrid, una ventana configurada diferente y el rechazo de una fecha imposible. No utilizan filas ni datos personales del CSV real. La suite completa pasa con 58 tests y 200 assertions; también pasan Pint y `git diff --check`.

Las pruebas automatizadas del cliente Shopify utilizan exclusivamente respuestas HTTP simuladas: comprueban URLs, intercambio de credenciales, encabezados, reutilización y renovación del token, configuración ausente, omisión de variables vacías, envío de variables nombradas y errores sin exposición de secretos. La suite completa pasa con 72 tests y 223 assertions; también pasan Pint y `git diff --check`.

La comprobación manual de conexión utilizó únicamente `shop { id }` y no imprimió el identificador. Laravel cargó los tres valores de configuración; el primer intento autenticó correctamente, pero Shopify devolvió `Invalid variables parameter.` por recibir `variables: []`. Tras omitir ese campo cuando está vacío, la misma consulta de solo lectura devolvió una respuesta válida. Ambas operaciones de prueba se validaron contra el esquema GraphQL `2026-10`. No se consultaron pedidos, no se modificó Shopify y no se abrió un navegador. Después de guardar las credenciales, `.env` siguió respondiendo `403` por HTTP y `public/` respondió `200`.
