# Requisitos funcionales — Integración NACEX con Shopify

## 1. Objetivo

La aplicación permitirá subir un CSV de NACEX, localizar en Shopify los pedidos correspondientes y completarlos con su número y URL de seguimiento.

La aplicación no utilizará base de datos propia. Antes de completar un pedido, consultará su estado actual en Shopify para evitar duplicados y correos repetidos.

---

## RF-01 — Cargar archivo CSV

El usuario podrá seleccionar y subir un archivo CSV exportado desde NACEX.

La aplicación deberá comprobar que:

- El archivo es un CSV.
- El archivo contiene datos.
- Existen las columnas necesarias.

Columnas obligatorias:

- `Ag. Origen`
- `Albarán`
- `Fecha alta`
- `Referencia`
- `CP Ent.`
- `Estado`

Si falta alguna columna, el archivo no se procesará.

---

## RF-02 — Leer correctamente el CSV

La aplicación deberá leer correctamente:

- El separador del archivo.
- Los nombres con tildes.
- La letra `ñ`.
- Los códigos postales con ceros iniciales.
- Los números de albarán como texto.

El albarán nunca deberá convertirse a decimal ni a notación científica.

---

## RF-03 — Filtrar las expediciones

La aplicación solo tendrá en cuenta las filas cuyo estado sea:

```text
Solicitada
```

La comparación no distinguirá entre mayúsculas y minúsculas ni tendrá en cuenta espacios sobrantes.

El resto de estados se ignorará.

---

## RF-04 — Evitar duplicados dentro del CSV

Si el archivo contiene varias filas con la misma agencia y el mismo albarán, la aplicación solo procesará una.

La combinación utilizada será:

```text
Agencia + Albarán
```

Ejemplo:

```text
0304 + 14910300
```

---

## RF-05 — Normalizar los datos

Antes de buscar el pedido, la aplicación deberá normalizar los datos.

### Agencia

La agencia tendrá cuatro dígitos:

```text
304 → 0304
```

### Código postal

El código postal español tendrá cinco dígitos:

```text
8019 → 08019
```

### Referencia

Para comparar nombres, la aplicación deberá:

- Convertir el texto a minúsculas.
- Eliminar tildes.
- Eliminar espacios sobrantes.
- Ignorar signos de puntuación.

La referencia original seguirá mostrándose sin modificar.

---

## RF-06 — Construir la URL de seguimiento

La aplicación construirá la URL utilizando la agencia y el albarán de cada fila.

Formato:

```text
https://www.nacex.es/seguimientoDetalle.do?agencia_origen={AGENCIA}&numero_albaran={ALBARAN}&estado=4&internacional=0&externo=N
```

Ejemplo:

```text
https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=14910300&estado=4&internacional=0&externo=N
```

---

## RF-07 — Buscar pedidos en Shopify

Por cada expedición, la aplicación buscará pedidos de Shopify que:

- No estén cancelados.
- Tengan productos pendientes de preparar.
- Pertenezcan a un periodo cercano a la fecha de alta de NACEX.

La búsqueda se realizará utilizando:

1. Nombre de la dirección de envío.
2. Código postal de la dirección de envío.
3. Fecha del pedido.

---

## RF-08 — Comparar la referencia con el cliente

Mientras la referencia NACEX contenga el nombre del cliente, se considerará una coincidencia cuando:

- El nombre de Shopify empiece por la referencia de NACEX.
- El código postal sea el mismo.
- La fecha del pedido sea compatible.
- Solo exista un pedido candidato.

Ejemplo:

```text
Referencia NACEX:
Paula Robles Rodrigu

Nombre Shopify:
Paula Robles Rodríguez
```

Esta comparación será válida si el código postal también coincide.

---

## RF-09 — Clasificar los resultados

Cada expedición deberá mostrar uno de estos resultados:

### Listo para completar

Existe un único pedido pendiente que coincide correctamente.

### Ya preparado

El pedido ya está completamente preparado.

No se realizará ninguna acción.

### Seguimiento existente

El pedido ya contiene el mismo número de albarán.

No se realizará ninguna acción.

### No encontrado

No se ha encontrado ningún pedido compatible.

### Coincidencia ambigua

Se han encontrado varios pedidos posibles.

No se completará ninguno automáticamente.

### Preparación parcial

El pedido tiene parte de los productos ya preparados.

No se procesará automáticamente en el MVP.

### Error

No se ha podido procesar la fila o consultar Shopify.

---

## RF-10 — Mostrar una vista previa

Antes de modificar Shopify, la aplicación mostrará una tabla con:

- Referencia NACEX.
- Agencia.
- Albarán.
- Código postal.
- Fecha de alta.
- URL de seguimiento.
- Pedido encontrado.
- Cliente de Shopify.
- Estado del pedido.
- Resultado del análisis.

La aplicación no realizará ningún cambio durante esta fase.

---

## RF-11 — Seleccionar pedidos

La aplicación seleccionará por defecto únicamente los pedidos clasificados como:

```text
Listo para completar
```

El usuario podrá:

- Seleccionar pedidos.
- Deseleccionar pedidos.
- Abrir el pedido en Shopify.
- Excluir una fila de la ejecución.

Los casos ambiguos, parciales, ya preparados o no encontrados no se seleccionarán automáticamente.

---

## RF-12 — Confirmar la ejecución

Antes de completar los pedidos, la aplicación mostrará:

- Número de pedidos seleccionados.
- Número de notificaciones que se enviarán.
- Opción para activar o desactivar la notificación al cliente.

El usuario deberá confirmar la operación.

---

## RF-13 — Revalidar el pedido

Justo antes de completar cada pedido, la aplicación volverá a consultar Shopify.

Deberá comprobar:

- Que el pedido sigue pendiente.
- Que no se ha preparado desde el análisis inicial.
- Que el albarán todavía no existe.
- Que sigue existiendo una única orden de preparación válida.

Si el pedido ha cambiado, se omitirá.

---

## RF-14 — Completar el pedido

Cuando la revalidación sea correcta, la aplicación creará el cumplimiento en Shopify con:

```text
Transportista: NACEX
Número de seguimiento: albarán del CSV
URL de seguimiento: URL generada
```

En el MVP se completarán todos los productos pendientes del pedido cuando exista una única preparación válida.

---

## RF-15 — Enviar la notificación

El usuario podrá decidir si Shopify debe enviar el correo de confirmación de envío.

La notificación solo se solicitará cuando se cree un cumplimiento nuevo.

No se enviará correo cuando:

- El pedido ya esté preparado.
- El seguimiento ya exista.
- La revalidación falle.
- El pedido no se complete.
- Se vuelva a subir el mismo CSV.

---

## RF-16 — Permitir varios CSV

El usuario podrá subir varios CSV durante el mismo día.

Cada archivo se procesará de manera independiente.

Antes de actuar, la aplicación consultará Shopify. Por tanto, si un pedido ya fue completado desde un CSV anterior:

- Se detectará como preparado.
- Se omitirá.
- No se modificará.
- No se enviará otro correo.

---

## RF-17 — Continuar ante errores

Cada pedido se procesará de forma independiente.

Si un pedido produce un error:

- Se mostrará el error.
- El resto de pedidos continuará procesándose.
- El usuario podrá reintentarlo durante la misma sesión.

Antes del reintento se volverá a consultar Shopify.

---

## RF-18 — Mostrar los resultados

Al finalizar, la aplicación mostrará:

- Pedidos completados.
- Pedidos ya preparados.
- Seguimientos existentes.
- Pedidos no encontrados.
- Coincidencias ambiguas.
- Errores.

Cada pedido encontrado incluirá un enlace directo a Shopify.

---

## RF-19 — Descargar informe

El usuario podrá descargar un CSV con el resultado de la ejecución.

El informe incluirá:

- Referencia.
- Agencia.
- Albarán.
- Código postal.
- Pedido Shopify.
- Resultado.
- Notificación enviada.
- Mensaje de error.

La aplicación no guardará el informe ni el historial después de cerrar o recargar la página.

---

## RF-20 — Funcionar sin base de datos

La aplicación no utilizará una base de datos propia.

Los datos del CSV y los resultados se mantendrán únicamente durante la sesión actual.

Shopify será la fuente de verdad para comprobar:

- Estado de preparación.
- Cumplimientos existentes.
- Seguimientos existentes.
- Posibilidad de completar el pedido.

---

## Flujo funcional resumido

```text
Subir CSV
    ↓
Validar columnas
    ↓
Filtrar estado Solicitada
    ↓
Eliminar duplicados del archivo
    ↓
Normalizar datos
    ↓
Construir URL de seguimiento
    ↓
Buscar pedido en Shopify
    ↓
Comparar nombre + CP + fecha
    ↓
Comprobar estado y tracking
    ↓
Mostrar vista previa
    ↓
Seleccionar pedidos
    ↓
Confirmar ejecución
    ↓
Revalidar cada pedido
    ↓
Completar pedido y enviar notificación
    ↓
Mostrar resultados
    ↓
Descargar informe opcional
```
