# Confirmación, revalidación y cumplimiento en Shopify

## Estado

RF-12 a RF-18 están implementados. La aplicación permite confirmar una selección, revalidar cada pedido inmediatamente antes de modificar Shopify, crear el cumplimiento NACEX y mostrar un resultado independiente por expedición. El informe descargable de RF-19 permanece pendiente.

Durante el desarrollo y la verificación automatizada no se ejecutaron mutaciones contra la tienda real. Las pruebas sustituyen el puerto Shopify o simulan las respuestas HTTP.

## Contrato de ejecución

El navegador envía al endpoint `POST /fulfillments/execute` el mismo archivo CSV seleccionado, la identidad textual `agencia + albarán`, el GID exacto del pedido, el tipo de asignación y la decisión de notificar.

El backend vuelve a validar e inspeccionar el CSV, filtra `Solicitada`, deduplica, normaliza y reconstruye la URL NACEX. No confía en un número o una URL de seguimiento proporcionados por el navegador. Los GID solo se aceptan con la forma exacta `gid://shopify/Order/{id numérico}` y una identidad repetida dentro de la selección se rechaza sin contactar Shopify.

## Confirmación RF-12

Antes de ejecutar, la interfaz muestra el número de pedidos pendientes, el número de notificaciones que se solicitarán, el control de notificación activado por defecto y la acción explícita “Confirmar y completar”. Mientras existe una petición activa, las acciones de confirmar y reintentar quedan deshabilitadas para impedir dobles clics. Cerrar el diálogo no inicia ninguna operación.

## Revalidación RF-13 y asignaciones

Cada fila se procesa de forma aislada. Inmediatamente antes de crear un cumplimiento se consulta el pedido por ID y se comprueba de nuevo:

- que el pedido existe y conserva un GID válido;
- que la fecha, el nombre de envío y el código postal aún coinciden con la expedición;
- que no está cancelado;
- que el albarán no aparece ya como seguimiento;
- que el pedido sigue `UNFULFILLED` y no tiene cumplimientos;
- que existe exactamente una orden de cumplimiento `OPEN`;
- que Shopify expone `CREATE_FULFILLMENT` entre sus acciones permitidas.

Una selección automática vuelve a ejecutar además la búsqueda por ventana temporal y debe seguir siendo una coincidencia única con el mismo pedido. Una asignación manual se valida directamente contra los datos actuales del pedido elegido. Si cualquier comprobación cambia, la fila termina como `SKIPPED_AFTER_REVALIDATION` y no se envía la mutación.

## Creación RF-14 y notificación RF-15

La mutación `fulfillmentCreate` utiliza empresa `NACEX`, el albarán textual, la URL NACEX reconstruida en backend y la única orden de cumplimiento validada. No se envía una lista parcial de líneas, por lo que Shopify completa todos los artículos restantes de esa orden. `notifyCustomer` solo se envía al crear un cumplimiento nuevo.

Las operaciones se validaron contra Admin GraphQL `2026-10`. La instalación debe disponer de `read_orders` y de los permisos de lectura y escritura de órdenes de cumplimiento aplicables a su modelo: `read_assigned_fulfillment_orders`/`write_assigned_fulfillment_orders`, `read_merchant_managed_fulfillment_orders`/`write_merchant_managed_fulfillment_orders` o `read_third_party_fulfillment_orders`/`write_third_party_fulfillment_orders` cuando correspondan. La disponibilidad real debe comprobarse antes de usar pedidos reales.

## Idempotencia y resultados RF-16 a RF-18

La precedencia de salida es:

1. `TRACKING_EXISTS` si el albarán ya figura en cualquier seguimiento.
2. `ALREADY_FULFILLED` si el pedido ya está preparado.
3. `SKIPPED_AFTER_REVALIDATION` para cancelación, cambio de coincidencia, parcialidad, cumplimiento previo, varias órdenes o cualquier estado no seguro.
4. `COMPLETED` únicamente después de que Shopify confirme una mutación sin `userErrors`.
5. `ERROR` cuando no se puede completar la consulta o la mutación.

Esta relectura hace seguro repetir el mismo archivo o reintentar tras una respuesta incierta: si el primer intento llegó a crear el cumplimiento, el siguiente detectará el seguimiento o el estado actual antes de escribir. Los detalles internos de Shopify no se exponen al navegador.

Los resultados se conservan solo en memoria. Cada fila muestra estado, mensaje y enlace administrativo validado cuando está disponible. Solo `ERROR` ofrece reintento directo; el reintento vuelve a pasar por toda la revalidación. Un fallo no detiene las filas posteriores.

## Arquitectura

- `PrepareNacexCsv` comparte la preparación segura entre análisis y ejecución.
- `ExecuteSelectedNacexShipments` coordina el procesamiento por fila y decide si es seguro escribir.
- `ShopifyFulfillmentGateway` es el contrato de aplicación para revalidar y crear.
- `Infrastructure/Shopify/ShopifyFulfillmentGateway` adapta las consultas y mutaciones GraphQL.
- `NacexFulfillmentController` y `ExecuteNacexFulfillmentsRequest` limitan la capa HTTP a validar, adaptar y responder.
- `FulfillmentExecutionPanel.vue` gestiona confirmación, estado de petición, resultados y reintento sin contener reglas críticas de Shopify.

No se introduce base de datos, sesión funcional ni persistencia de resultados.

## Verificación

Las pruebas cubren creación segura, opción de notificación, precedencia del seguimiento, aislamiento de errores, rechazo de selecciones duplicadas y GID inválidos, revalidación GraphQL, payload de `fulfillmentCreate`, ausencia de pedido y `userErrors` seguros. Las consultas y mutación usan datos ficticios y transporte HTTP simulado.

También se verifican formato PHP, tipos Vue/TypeScript y build de producción. La interfaz mantiene foco visible, diálogo centrado, feedback de pulsación solo en dispositivos con hover y `prefers-reduced-motion`.
