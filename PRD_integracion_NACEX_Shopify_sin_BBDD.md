# PRD — Integración de expediciones NACEX con Shopify mediante CSV

**Producto:** SCRAPEO – Seguimientos NACEX → Shopify  
**Versión:** 1.1  
**Estado:** Definición funcional del MVP  
**Fecha:** 1 de agosto de 2026  
**Responsable de producto:** Alejandro  

---

## 1. Resumen ejecutivo

Se desarrollará una aplicación interna que permita importar un archivo CSV exportado desde NACEX, identificar las expediciones registradas por primera vez y completar automáticamente sus pedidos correspondientes en Shopify.

La aplicación utilizará únicamente las filas cuyo estado sea **`Solicitada`**, ya que representan el primer registro de cada expedición. Los estados posteriores de NACEX, como `En tránsito`, `Incidencia`, `En reparto` o `Entregado`, no se utilizarán en esta primera versión.

De cada expedición se extraerán principalmente:

- Referencia.
- Agencia de origen.
- Número de albarán.
- Código postal de entrega.
- Fecha de alta.
- Estado.

La referencia contiene actualmente el nombre del cliente. La aplicación buscará en Shopify un pedido abierto y no preparado cuyo destinatario coincida con dicha referencia y cuyo código postal coincida con el del CSV.

Cuando exista una única coincidencia segura, la aplicación generará la URL de seguimiento de NACEX y completará el pedido en Shopify.

La aplicación **no utilizará una base de datos propia**. Shopify será la fuente de verdad para determinar si un pedido ya está preparado o si el seguimiento ya ha sido asignado. Esto permitirá subir varios CSV durante el mismo día, incluso archivos que contengan expediciones repetidas, sin volver a completar pedidos ni reenviar correos de confirmación.

En una fase posterior, la referencia de NACEX contendrá directamente el número de pedido de Shopify, lo que permitirá una correspondencia exacta y simplificará considerablemente el proceso.

---

## 2. Contexto y problema

Actualmente, después de generar las expediciones en NACEX, el equipo debe localizar manualmente cada pedido en Shopify, introducir el número de seguimiento y marcarlo como preparado.

Este proceso presenta varios problemas:

- Consume tiempo operativo.
- Obliga a copiar manualmente números de albarán.
- Existe riesgo de asignar un seguimiento al pedido incorrecto.
- Puede olvidarse algún pedido.
- Puede enviarse información incorrecta al cliente.
- Una misma expedición aparece varias veces en el CSV debido a sus diferentes estados logísticos.
- La importación repetida de un CSV podría provocar acciones duplicadas si no se valida el estado actual del pedido.
- Un pedido podría recibir más de una notificación si no se comprueba si ya fue preparado.

El objetivo es reducir este proceso a:

1. Exportar el CSV desde NACEX.
2. Subirlo a la aplicación.
3. Revisar las coincidencias.
4. Confirmar la preparación de los pedidos.
5. Descargar opcionalmente un informe de la ejecución.

---

## 3. Objetivo del producto

Automatizar la asignación de seguimientos NACEX y la preparación de pedidos en Shopify a partir de un CSV, minimizando la intervención manual y evitando completar pedidos incorrectos o duplicados.

### 3.1 Objetivos específicos

- Procesar únicamente expediciones nuevas según el estado `Solicitada`.
- Relacionar cada expedición con su pedido de Shopify.
- Construir automáticamente la URL de seguimiento.
- Añadir el seguimiento NACEX al pedido.
- Marcar el pedido como preparado.
- Notificar opcionalmente al cliente.
- Evitar preparar dos veces el mismo pedido.
- Evitar añadir dos veces el mismo número de seguimiento.
- Evitar enviar varias veces el correo de confirmación de envío.
- Permitir subir varios CSV durante el mismo día.
- Permitir volver a subir un CSV ya procesado sin provocar cambios duplicados.
- Mostrar claramente los registros que requieren revisión manual.
- Generar un informe descargable de la ejecución sin almacenar datos en una BBDD propia.

---

## 4. Principios de diseño

### 4.1 Sin base de datos propia

La aplicación no tendrá MySQL, PostgreSQL, SQLite ni ninguna otra base de datos.

No se almacenarán de forma persistente:

- Importaciones anteriores.
- Albaranes procesados.
- Correspondencias entre expediciones y pedidos.
- Historial de ejecuciones.
- Errores de ejecuciones anteriores.
- Datos personales de clientes.

Cada carga de CSV será una ejecución independiente.

### 4.2 Shopify como fuente de verdad

Antes de realizar cualquier cambio, la aplicación consultará el estado actualizado del pedido en Shopify.

Shopify determinará si:

- El pedido sigue pendiente de preparación.
- El pedido ya está completamente preparado.
- El pedido está parcialmente preparado.
- El albarán ya está registrado.
- Ya existe un cumplimiento con seguimiento.
- Se puede crear un nuevo cumplimiento.

### 4.3 Idempotencia mediante validación en tiempo real

La aplicación deberá poder procesar varias veces el mismo CSV sin duplicar acciones.

La protección contra duplicados se basará en:

- Estado actual del pedido.
- Cumplimientos existentes.
- Números de seguimiento existentes.
- Revalidación inmediatamente antes de crear el cumplimiento.

### 4.4 Prioridad absoluta de la seguridad

La aplicación solo completará automáticamente pedidos cuando exista una única coincidencia segura.

Ante cualquier duda:

- No completará el pedido.
- No añadirá seguimiento.
- No enviará correo.
- Marcará la fila para revisión manual.

La precisión tendrá prioridad sobre el porcentaje de automatización.

---

## 5. Usuarios

### 5.1 Usuario principal

Personal interno responsable de la preparación y gestión de pedidos de Shopify.

### 5.2 Administrador técnico

Persona responsable de:

- Configurar las credenciales de Shopify.
- Gestionar los permisos de la aplicación.
- Revisar errores técnicos.
- Mantener la integración.
- Modificar parámetros de funcionamiento.

No habrá acceso para clientes finales.

---

## 6. Alcance del MVP

La primera versión incluirá:

1. Carga manual de un archivo CSV.
2. Validación de estructura y columnas.
3. Lectura del CSV con codificación compatible con caracteres españoles.
4. Filtrado por estado `Solicitada`.
5. Eliminación de expediciones duplicadas dentro del mismo archivo.
6. Normalización de agencia, albarán, referencia y código postal.
7. Construcción de la URL de seguimiento.
8. Consulta de pedidos no preparados en Shopify.
9. Matching mediante:
   - Nombre del destinatario.
   - Código postal.
   - Fecha del pedido.
   - Estado de preparación.
10. Clasificación de resultados.
11. Vista previa antes de realizar cambios.
12. Confirmación manual de las coincidencias seguras.
13. Revalidación del pedido justo antes de completarlo.
14. Creación del cumplimiento en Shopify.
15. Asignación del número y URL de seguimiento.
16. Notificación configurable al cliente.
17. Prevención de duplicados consultando Shopify.
18. Procesamiento independiente de cada fila.
19. Informe descargable de resultados.
20. Soporte para múltiples CSV durante el mismo día.

---

## 7. Fuera del alcance del MVP

No se incluirá inicialmente:

- Base de datos propia.
- Historial persistente de importaciones.
- Registro persistente de albaranes procesados.
- Scraping de la página de NACEX.
- Consulta automática de estados posteriores.
- Sincronización del estado `En tránsito`.
- Sincronización de incidencias.
- Sincronización del estado `Entregado`.
- Actualización periódica del seguimiento.
- Descarga automática del CSV desde NACEX.
- Procesamiento automático de archivos recibidos por correo.
- Preparación automática sin revisión previa.
- Procesamiento de devoluciones.
- Procesamiento de recogidas.
- Gestión automática de pedidos parcialmente enviados.
- Gestión automática de pedidos divididos entre varias ubicaciones.
- Aplicación pública dentro de Shopify App Store.
- Panel visible para clientes.
- Recuperación de informes de ejecuciones anteriores.
- Reanudación de una ejecución interrumpida después de cerrar la aplicación.

---

## 8. Archivo de entrada

### 8.1 Formato

- Tipo: CSV.
- Separador esperado: punto y coma `;`.
- Codificación esperada: ISO-8859-1, Windows-1252 o UTF-8.
- Primera fila: cabeceras.
- Una fila por evento de seguimiento.

### 8.2 Columnas necesarias

| Columna | Obligatoria | Uso |
|---|---:|---|
| Ag. Origen | Sí | Construcción de la URL |
| Albarán | Sí | Número de seguimiento |
| Fecha alta | Sí | Matching temporal y auditoría de sesión |
| Referencia | Sí | Búsqueda del pedido |
| CP Ent. | Sí | Validación del destinatario |
| Estado | Sí | Selección de expediciones nuevas |
| Ag. Estado | No | Información auxiliar |
| Fecha Estado | No | Información auxiliar |
| Hora Estado | No | Información auxiliar |
| Descripción | No | Información adicional |
| Bultos | No | Información adicional |
| pistoleo | No | No utilizado |

Las columnas vacías adicionales deberán ignorarse.

### 8.3 Tamaño máximo inicial

El MVP deberá aceptar archivos de hasta:

- 5 MB.
- 1.000 filas de datos, sin contar la cabecera ni líneas vacías.

Estos límites serán configurables.

---

## 9. Reglas de procesamiento del CSV

### BR-001 — Estado permitido

Solo se procesarán las filas cuyo estado normalizado sea:

```text
Solicitada
```

La comparación ignorará:

- Mayúsculas y minúsculas.
- Espacios al principio y al final.
- Espacios duplicados.
- Diferencias de codificación razonables.

Ejemplos aceptados:

```text
Solicitada
SOLICITADA
 solicitada
Solicitada 
```

### BR-002 — Estados ignorados

Se ignorarán, entre otros:

```text
Recogido, en tránsito pendiente
En tránsito
Incidencia
En reparto
Entregado
```

### BR-003 — Identificador de expedición durante la sesión

Una expedición se identificará dentro del archivo mediante la combinación:

```text
agencia_origen + numero_albaran
```

Ejemplo:

```text
0304-14910300
```

Este identificador se utilizará únicamente durante la ejecución actual y no se guardará de forma persistente.

### BR-004 — Eliminación de duplicados dentro del CSV

Si el archivo contiene más de una fila con estado `Solicitada` para la misma agencia y albarán, solo se analizará una.

### BR-005 — Agencia de origen

La agencia se normalizará a cuatro dígitos, añadiendo ceros a la izquierda.

Ejemplos:

```text
304  → 0304
25   → 0025
1304 → 1304
```

### BR-006 — Número de albarán

El albarán se tratará siempre como texto.

No deberá:

- Convertirse en decimal.
- Perder ceros iniciales.
- Mostrar notación científica.
- Ser redondeado.

Ejemplo:

```text
14910300
```

### BR-007 — Código postal

El código postal español se normalizará a cinco dígitos.

Ejemplos:

```text
8019 → 08019
3400 → 03400
8912 → 08912
```

### BR-008 — Referencia

La referencia:

- Se tratará como texto.
- Se limpiará de espacios iniciales y finales.
- No se modificará en la vista original.
- Se generará además una versión normalizada para comparar con Shopify.

### BR-009 — Fecha de alta

La fecha de alta se utilizará para reducir el riesgo de asociar un albarán antiguo a un pedido nuevo del mismo cliente.

La aplicación deberá admitir los formatos presentes en el CSV real de NACEX.

---

## 10. Construcción de la URL de seguimiento

La URL seguirá esta plantilla:

```text
https://www.nacex.es/seguimientoDetalle.do?agencia_origen={AGENCIA}&numero_albaran={ALBARAN}&estado=4&internacional=0&externo=N
```

Ejemplo:

```text
Agencia: 0304
Albarán: 14910300
```

Resultado:

```text
https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=14910300&estado=4&internacional=0&externo=N
```

Solo cambiarán dinámicamente:

- `agencia_origen`
- `numero_albaran`

Los siguientes parámetros se configurarán inicialmente como constantes:

```text
estado=4
internacional=0
externo=N
```

Estas constantes deberán estar centralizadas en la configuración de la aplicación.

---

## 11. Búsqueda de pedidos en Shopify

### 11.1 Pedidos candidatos

La aplicación consultará pedidos que cumplan inicialmente estas condiciones:

- Pedido no cancelado.
- Pedido con artículos pendientes de preparar.
- Pedido dentro de una ventana temporal configurable.
- Pedido cuyo nombre o destinatario pueda coincidir con la referencia.
- Pedido cuyo código postal pueda coincidir con el CSV.

La ventana temporal inicial recomendada será:

```text
Desde 30 días antes hasta 7 días después de la fecha de alta de NACEX
```

La ventana deberá ser configurable.

### 11.2 Nombre utilizado

El orden de prioridad será:

1. Nombre completo de la dirección de envío.
2. Nombre y apellidos de la dirección de envío.
3. Nombre del cliente.
4. Nombre de la dirección de facturación, únicamente como respaldo.

La dirección de envío tendrá prioridad porque representa a la persona que recibe el paquete.

### 11.3 Referencia truncada

La referencia de NACEX puede estar limitada a una longitud fija y contener un nombre incompleto.

Ejemplos:

```text
Noelia Fernández Mol
Maria Asuncion Rodri
Paula Robles Rodrigu
Barbara Rodriguez Ru
```

Por tanto, no se exigirá igualdad exacta entre la referencia de NACEX y el nombre completo de Shopify.

### 11.4 Normalización de nombres

Antes de comparar nombres se aplicarán las siguientes transformaciones:

- Conversión a minúsculas.
- Eliminación de tildes.
- Conversión de `ñ` de forma coherente.
- Eliminación de signos de puntuación.
- Sustitución de varios espacios por uno.
- Eliminación de espacios iniciales y finales.
- Normalización de apóstrofes y guiones.
- Conservación del orden del nombre y apellidos.

Ejemplo:

```text
NACEX:
Ana Belén García Cas

Normalizado:
ana belen garcia cas
```

```text
Shopify:
Ana Belén García Castro

Normalizado:
ana belen garcia castro
```

La referencia NACEX coincide con el inicio del nombre de Shopify.

### 11.5 Regla de coincidencia segura del MVP

Una coincidencia será considerada segura cuando:

```text
El nombre normalizado de Shopify comienza por la referencia normalizada de NACEX
Y
El código postal normalizado coincide
Y
El pedido tiene artículos pendientes de preparar
Y
La fecha del pedido es compatible con la fecha de alta de NACEX
Y
Solo existe un pedido que cumple todas las condiciones
```

Ejemplo:

```text
Referencia NACEX:
paula robles rodrigu

Nombre Shopify:
paula robles rodriguez

Código postal NACEX:
08105

Código postal Shopify:
08105
```

Resultado:

```text
MATCHED_SAFE
```

### 11.6 Coincidencia inversa

También podrá aceptarse:

```text
La referencia NACEX comienza por el nombre normalizado de Shopify
```

Solo se permitirá cuando:

- La diferencia de longitud sea pequeña.
- El código postal coincida.
- La fecha sea compatible.
- Solo exista un candidato.

### 11.7 Coincidencia aproximada

El MVP no utilizará similitud difusa para completar automáticamente.

Los algoritmos aproximados podrán utilizarse únicamente para mostrar sugerencias de revisión manual.

Ejemplos que no deberán completarse automáticamente:

```text
María López
María Luisa López
```

```text
Sonia Sestelo
Sonia Sotelo
```

---

## 12. Riesgo de reutilización del nombre

Mientras la referencia NACEX sea el nombre del cliente, existe el siguiente riesgo:

1. Se crea un envío para un cliente.
2. El pedido se prepara correctamente.
3. Días después, el mismo cliente realiza otro pedido.
4. Se vuelve a subir un CSV antiguo.
5. La aplicación encuentra el nuevo pedido no preparado del mismo cliente.

Para reducir este riesgo, la aplicación deberá comprobar conjuntamente:

- Nombre.
- Código postal.
- Fecha del pedido.
- Fecha de alta de NACEX.
- Estado de preparación.
- Seguimientos existentes.
- Número de candidatos.

Si sigue existiendo cualquier duda, el resultado será `AMBIGUOUS` o `NO_MATCH` y no se realizará ningún cambio.

Este riesgo desaparecerá en gran medida cuando la referencia NACEX contenga el número de pedido de Shopify.

---

## 13. Clasificación de resultados

Cada fila procesada se clasificará en uno de los siguientes estados.

### MATCHED_SAFE

Existe exactamente un pedido que cumple todas las reglas de coincidencia.

La expedición puede seleccionarse para completar el pedido.

### NO_MATCH

No existe ningún pedido compatible.

Posibles causas:

- El nombre no coincide.
- El código postal es diferente.
- La fecha no es compatible.
- El pedido ya no está pendiente.
- La referencia no corresponde a ningún pedido.

### AMBIGUOUS

Existen dos o más pedidos compatibles o la aplicación no puede determinar con seguridad cuál corresponde.

No se completará ninguno automáticamente.

### ALREADY_FULFILLED

El pedido correspondiente ya está completamente preparado.

La fila se omitirá.

No se modificará el pedido.

No se enviará ninguna notificación.

### TRACKING_EXISTS

El pedido ya tiene asignado el mismo número de albarán.

La fila se omitirá.

No se modificará el pedido.

No se enviará ninguna notificación.

### FULFILLMENT_EXISTS

El pedido ya tiene un cumplimiento compatible, aunque el seguimiento no pueda compararse de forma concluyente.

La fila requerirá revisión o se omitirá según configuración.

### PARTIAL_FULFILLMENT

El pedido está parcialmente preparado o contiene varias órdenes de cumplimiento.

Requerirá revisión manual en el MVP.

### INVALID_ROW

La fila no contiene algún campo obligatorio o presenta un formato inválido.

### IGNORED_STATUS

La fila se ignora porque su estado no es `Solicitada`.

### READY_TO_PROCESS

El pedido ha sido validado en la fase de análisis y está listo para una revalidación final.

### COMPLETED

El cumplimiento se ha creado correctamente.

### SKIPPED_AFTER_REVALIDATION

Durante la revalidación final se ha detectado que el pedido ya fue preparado o modificado desde el análisis inicial.

---

## 14. Flujo de usuario

### Paso 1 — Acceso

El usuario accede al panel interno de la aplicación.

### Paso 2 — Carga

El usuario selecciona o arrastra un archivo CSV de NACEX.

### Paso 3 — Validación

La aplicación comprueba:

- Tipo de archivo.
- Tamaño.
- Codificación.
- Separador.
- Presencia de cabeceras obligatorias.
- Existencia de filas.
- Formato de albaranes.
- Formato de agencias.
- Formato de códigos postales.
- Formato de fechas.

### Paso 4 — Análisis

La aplicación:

1. Lee el CSV.
2. Filtra las filas `Solicitada`.
3. Elimina duplicados dentro del archivo.
4. Normaliza los datos.
5. Construye las URL.
6. Consulta Shopify.
7. Busca coincidencias.
8. Comprueba el estado de preparación.
9. Comprueba seguimientos existentes.
10. Clasifica cada registro.

### Paso 5 — Vista previa

Se muestra una tabla con los resultados antes de modificar Shopify.

### Paso 6 — Selección

Por defecto se seleccionan únicamente los registros:

```text
MATCHED_SAFE
```

Los registros ambiguos, inválidos, ya preparados o no encontrados no se seleccionan.

### Paso 7 — Confirmación

El usuario pulsa:

```text
Completar pedidos seleccionados
```

La aplicación muestra una confirmación con:

- Número de pedidos seleccionados.
- Número de clientes que recibirán notificación.
- Número de expediciones.
- Advertencias existentes.

### Paso 8 — Revalidación final

Antes de modificar cada pedido, la aplicación vuelve a consultar Shopify.

Comprueba nuevamente:

- Estado de preparación.
- Cumplimientos existentes.
- Números de seguimiento existentes.
- Disponibilidad de los artículos para cumplir.
- Validez de la coincidencia.

### Paso 9 — Ejecución

Solo si la revalidación es correcta:

1. Se crea el cumplimiento.
2. Se añade el seguimiento.
3. Se decide si Shopify debe enviar la notificación.
4. Se recoge el resultado de la API.

### Paso 10 — Resultado

Se muestra:

- Pedidos completados.
- Pedidos omitidos.
- Pedidos ya preparados.
- Seguimientos ya existentes.
- Errores.
- Advertencias.
- Enlaces a los pedidos de Shopify.

### Paso 11 — Informe

El usuario puede descargar un CSV de resultados.

El informe no se guardará en la aplicación.

---

## 15. Interfaz del MVP

### 15.1 Pantalla de importación

Elementos:

- Selector de archivo.
- Área de arrastrar y soltar.
- Botón `Analizar CSV`.
- Información sobre columnas esperadas.
- Aviso de que no existe historial persistente.

### 15.2 Resumen del análisis

Ejemplo de indicadores:

| Indicador | Ejemplo |
|---|---:|
| Filas totales | 69 |
| Expediciones solicitadas | 17 |
| Coincidencias seguras | 14 |
| Ya preparadas | 1 |
| Seguimientos existentes | 0 |
| Sin coincidencia | 1 |
| Ambiguas | 1 |

### 15.3 Tabla de resultados

| Columna | Contenido |
|---|---|
| Seleccionar | Checkbox |
| Resultado | Estado del análisis |
| Referencia NACEX | Nombre o referencia |
| Código postal | CP normalizado |
| Fecha alta | Fecha NACEX |
| Agencia | Agencia normalizada |
| Albarán | Número de seguimiento |
| Pedido Shopify | Número y enlace |
| Cliente Shopify | Nombre completo |
| Fecha pedido | Fecha de creación |
| Estado Shopify | No preparado, parcial o preparado |
| Tracking existente | Sí o no |
| URL NACEX | URL construida |
| Acción | Revisar, excluir o abrir |

### 15.4 Filtros

- Todos.
- Coincidencias seguras.
- Ya preparados.
- Seguimiento existente.
- Sin coincidencia.
- Ambiguos.
- Errores.

### 15.5 Acciones

- Analizar CSV.
- Seleccionar coincidencias seguras.
- Deseleccionar todos.
- Completar pedidos seleccionados.
- Descargar informe.
- Abrir pedido en Shopify.
- Reintentar registro durante la sesión.
- Excluir registro.

---

## 16. Preparación del pedido en Shopify

Para cada coincidencia aprobada, la aplicación deberá:

1. Volver a consultar el pedido.
2. Verificar que sigue pendiente de preparación.
3. Obtener sus órdenes de cumplimiento.
4. Consultar los cumplimientos existentes.
5. Consultar los números de seguimiento existentes.
6. Confirmar que el albarán no está ya asignado.
7. Confirmar que los artículos pueden prepararse.
8. Crear el cumplimiento.
9. Añadir los datos de seguimiento.
10. Recoger el resultado.

### 16.1 Datos de seguimiento

```json
{
  "company": "NACEX",
  "number": "14910300",
  "url": "https://www.nacex.es/seguimientoDetalle.do?agencia_origen=0304&numero_albaran=14910300&estado=4&internacional=0&externo=N"
}
```

### 16.2 Transportista

```text
NACEX
```

### 16.3 Número de seguimiento

Se utilizará el número de albarán del CSV.

### 16.4 URL de seguimiento

Se utilizará la URL construida por la aplicación.

### 16.5 Notificación al cliente

La interfaz incluirá una opción:

```text
☑ Enviar notificación de envío al cliente
```

Valor predeterminado recomendado:

```text
Activado
```

La notificación solo podrá enviarse cuando se cree un cumplimiento nuevo.

Si el pedido ya está preparado o el seguimiento ya existe:

- No se ejecutará la mutación.
- No se solicitará a Shopify el envío del correo.
- La fila se marcará como omitida.

### 16.6 Artículos preparados

En el MVP:

- Se prepararán todos los artículos pendientes cuando exista una única orden de cumplimiento válida.
- Los pedidos parcialmente preparados pasarán a revisión manual.
- Los pedidos con varias ubicaciones pasarán a revisión manual.
- No se modificarán artículos ya preparados.

---

## 17. Prevención de duplicados sin base de datos

### 17.1 Regla principal

Antes de crear un cumplimiento, la aplicación deberá consultar Shopify y aplicar las siguientes reglas:

```text
Pedido completamente preparado
→ Omitir

Mismo albarán ya asignado
→ Omitir

Cumplimiento existente y situación no concluyente
→ Revisión manual

Pedido parcialmente preparado
→ Revisión manual

Pedido pendiente, coincidencia única y sin tracking
→ Completar
```

### 17.2 Subida repetida del mismo CSV

Primera subida:

```text
Referencia: Ana Belén García
Albarán: 14910300
Pedido: #58321
Estado: No preparado
```

Resultado:

```text
Pedido completado
Tracking añadido
Correo enviado
```

Segunda subida:

```text
Referencia: Ana Belén García
Albarán: 14910300
Pedido: #58321
Estado: Preparado
Tracking: 14910300
```

Resultado:

```text
ALREADY_FULFILLED o TRACKING_EXISTS
```

No se volverá a:

- Preparar el pedido.
- Editar el cumplimiento.
- Añadir el tracking.
- Enviar el correo.

### 17.3 Varios CSV en el mismo día

Cada CSV se analizará de manera independiente.

Si un pedido fue completado desde un CSV anterior:

- Shopify mostrará el pedido como preparado.
- La nueva ejecución lo detectará.
- La fila se omitirá.

No será necesario conocer ni almacenar qué archivo lo procesó anteriormente.

### 17.4 Doble clic o procesos simultáneos

El botón de ejecución se desactivará mientras exista una operación activa.

Además, cada pedido se revalidará inmediatamente antes de crear el cumplimiento.

Si otro proceso lo ha preparado entre el análisis y la ejecución:

```text
SKIPPED_AFTER_REVALIDATION
```

No se enviará ninguna notificación adicional.

---

## 18. Requisitos funcionales

### FR-001 — Subir CSV

El usuario podrá subir un archivo CSV desde su ordenador.

### FR-002 — Validar archivo

La aplicación rechazará archivos que no contengan las columnas obligatorias.

### FR-003 — Leer caracteres españoles

La aplicación deberá leer correctamente:

```text
á, é, í, ó, ú, ñ, ü
```

### FR-004 — Filtrar expediciones

Solo se analizarán filas con estado `Solicitada`.

### FR-005 — Deduplicar dentro del archivo

No se analizará dos veces la misma combinación de agencia y albarán dentro de una importación.

### FR-006 — Normalizar agencia

La agencia se guardará durante la sesión con cuatro dígitos.

### FR-007 — Normalizar código postal

El código postal español se manejará con cinco dígitos.

### FR-008 — Construir URL

La aplicación construirá una URL válida por expedición.

### FR-009 — Consultar Shopify

La aplicación recuperará los pedidos candidatos mediante Shopify Admin API.

### FR-010 — Comparar destinatario

La referencia se comparará principalmente con el nombre de la dirección de envío.

### FR-011 — Comparar código postal

El código postal deberá coincidir para que el matching sea seguro.

### FR-012 — Comparar fechas

La fecha del pedido deberá ser compatible con la fecha de alta de NACEX.

### FR-013 — Clasificar coincidencias

Cada expedición recibirá un estado de análisis.

### FR-014 — Vista previa

No se modificará Shopify durante el análisis inicial.

### FR-015 — Selección manual

El usuario podrá elegir qué coincidencias procesar.

### FR-016 — Bloqueo de casos ambiguos

La aplicación no seleccionará automáticamente registros ambiguos.

### FR-017 — Revalidación final

La aplicación volverá a consultar el pedido antes de crear el cumplimiento.

### FR-018 — Consultar cumplimiento existente

La aplicación comprobará si el pedido ya está preparado o tiene un cumplimiento previo.

### FR-019 — Consultar tracking existente

La aplicación comprobará si el albarán ya aparece en los seguimientos del pedido.

### FR-020 — Crear cumplimiento

La aplicación completará el pedido seleccionado mediante Shopify Admin API.

### FR-021 — Añadir seguimiento

El cumplimiento incluirá transportista, albarán y URL.

### FR-022 — Notificar cliente

El usuario podrá activar o desactivar la notificación de envío.

### FR-023 — No repetir notificaciones

La aplicación no enviará notificación si no crea un cumplimiento nuevo.

### FR-024 — Procesamiento independiente

El fallo de un pedido no deberá detener el procesamiento de los demás.

### FR-025 — Descargar informe

El usuario podrá descargar los resultados de la sesión en CSV.

### FR-026 — Enlace a Shopify

Cada resultado encontrado incluirá un enlace directo al pedido.

### FR-027 — Sin persistencia

Al recargar o cerrar la aplicación, los resultados de la sesión podrán perderse.

### FR-028 — Múltiples archivos

El usuario podrá procesar varios CSV de manera consecutiva.

### FR-029 — CSV repetido

La aplicación deberá permitir volver a subir el mismo archivo sin duplicar cumplimientos.

### FR-030 — Estado actualizado

La decisión final siempre se basará en el estado actual de Shopify, no en datos almacenados localmente.

---

## 19. Requisitos no funcionales

### NFR-001 — Seguridad

Las credenciales de Shopify deberán almacenarse mediante variables de entorno o un sistema seguro de secretos.

Nunca aparecerán en:

- Interfaz.
- Archivos CSV.
- Mensajes de error.
- Repositorio Git.
- Código fuente publicado.

### NFR-002 — Acceso restringido

La aplicación requerirá autenticación o acceso protegido dentro de la red interna.

### NFR-003 — Sin persistencia de datos personales

La aplicación no almacenará de forma permanente:

- Nombres.
- Direcciones.
- Códigos postales.
- Números de pedido.
- Albaranes.
- Resultados.

### NFR-004 — Rendimiento

Un CSV de hasta 1.000 filas deberá poder analizarse sin bloquear la interfaz.

### NFR-005 — Tolerancia a errores

Un error en una fila no deberá interrumpir toda la ejecución.

### NFR-006 — Protección contra doble clic

El botón de ejecución deberá desactivarse mientras se procesan pedidos.

### NFR-007 — Reintentos

Los errores temporales de red podrán reintentarse un número limitado de veces.

Los reintentos deberán volver a validar el estado del pedido antes de crear el cumplimiento.

### NFR-008 — Compatibilidad

La interfaz deberá funcionar en las últimas versiones de:

- Chrome.
- Firefox.
- Edge.

### NFR-009 — Privacidad

Los datos de clientes se mantendrán únicamente en memoria durante el tiempo necesario para completar la ejecución.

### NFR-010 — Logs técnicos

Los logs de aplicación no deberán contener nombres completos, direcciones ni credenciales.

Podrán contener identificadores técnicos mínimos para depuración, siempre que no constituyan un historial funcional de operaciones.

### NFR-011 — Respuesta ante límites de Shopify

La aplicación deberá respetar los límites de Shopify Admin API y gestionar esperas o reintentos cuando sea necesario.

### NFR-012 — Consistencia

Una fila solo se marcará como `COMPLETED` cuando Shopify confirme que el cumplimiento fue creado correctamente.

---

## 20. Datos temporales durante una ejecución

Aunque no exista BBDD, la aplicación necesitará mantener temporalmente en memoria los datos de la sesión.

### 20.1 ImportSession

| Campo | Uso |
|---|---|
| filename | Nombre del archivo cargado |
| totalRows | Filas totales |
| requestedRows | Filas con estado `Solicitada` |
| uniqueShipments | Expediciones únicas |
| results | Resultado temporal por fila |
| startedAt | Inicio de la sesión |
| completedAt | Fin de la sesión |

### 20.2 ShipmentResult

| Campo | Uso |
|---|---|
| originAgency | Agencia normalizada |
| trackingNumber | Albarán |
| reference | Referencia original |
| normalizedReference | Referencia normalizada |
| postalCode | Código postal normalizado |
| nacexStatus | Estado del CSV |
| shipmentDate | Fecha de alta |
| trackingUrl | URL construida |
| matchStatus | Resultado del matching |
| shopifyOrderId | ID temporal del pedido |
| shopifyOrderName | Número visible del pedido |
| processingStatus | Resultado de ejecución |
| errorMessage | Error durante la sesión |

Estos datos desaparecerán al finalizar o recargar la aplicación, salvo que el usuario descargue el informe.

---

## 21. Permisos de Shopify

La aplicación deberá contar únicamente con los permisos necesarios para:

- Consultar pedidos.
- Consultar direcciones de envío.
- Consultar órdenes de cumplimiento.
- Consultar cumplimientos y seguimientos existentes.
- Crear cumplimientos.

Los permisos definitivos dependerán de:

- Tipo de aplicación Shopify.
- Ubicaciones de inventario.
- Modelo de fulfillment de la tienda.
- Versión de Shopify Admin API utilizada.

---

## 22. Tratamiento de errores

### 22.1 CSV sin columnas obligatorias

Mensaje:

```text
El archivo no contiene todas las columnas necesarias.
Faltan: Referencia, Estado.
```

### 22.2 CSV sin expediciones solicitadas

Mensaje:

```text
No se han encontrado filas con estado «Solicitada».
No se realizará ningún cambio en Shopify.
```

### 22.3 Pedido no encontrado

Resultado:

```text
NO_MATCH
```

Acción:

- No completar.
- Permitir revisión manual.
- Mostrar referencia, código postal y fecha.

### 22.4 Varios pedidos encontrados

Resultado:

```text
AMBIGUOUS
```

Acción:

- No completar.
- Mostrar los candidatos.
- Permitir revisión manual.

### 22.5 Pedido ya preparado

Resultado:

```text
ALREADY_FULFILLED
```

Acción:

- No crear un nuevo cumplimiento.
- No enviar correo.
- Mostrar enlace al pedido.

### 22.6 Seguimiento ya existente

Resultado:

```text
TRACKING_EXISTS
```

Acción:

- No crear un nuevo cumplimiento.
- No modificar el tracking.
- No enviar correo.

### 22.7 Pedido parcialmente preparado

Resultado:

```text
PARTIAL_FULFILLMENT
```

Acción:

- No completar automáticamente.
- Mostrar para revisión.

### 22.8 Pedido modificado durante la ejecución

Resultado:

```text
SKIPPED_AFTER_REVALIDATION
```

Acción:

- No realizar cambios.
- No enviar correo.
- Mostrar el nuevo estado.

### 22.9 Error de Shopify

Acción:

- Mostrar una explicación comprensible.
- Continuar con los demás pedidos.
- Permitir reintento durante la sesión.
- Revalidar antes de reintentar.

### 22.10 Falta de permisos

Mensaje:

```text
La aplicación no tiene permisos para preparar pedidos en Shopify.
Revisa los permisos de la integración.
```

---

## 23. Informe de resultados

El informe descargable contendrá:

| Campo |
|---|
| Referencia NACEX |
| Agencia |
| Albarán |
| Código postal |
| Fecha alta |
| Estado NACEX |
| URL de seguimiento |
| Resultado del matching |
| Pedido Shopify |
| Cliente Shopify |
| Fecha pedido |
| Estado previo del pedido |
| Resultado del cumplimiento |
| Notificación solicitada |
| Fecha de procesamiento |
| Mensaje de error |

Ejemplo:

```csv
referencia;agencia;albaran;cp;pedido;resultado;notificacion
Ana Belén García;0304;14910300;14100;#58321;COMPLETADO;SI
María Rodríguez;0304;14910342;28001;;NO_MATCH;NO
Sonia Torralbo;0304;14911145;23770;#58344;ALREADY_FULFILLED;NO
```

El informe se generará en el navegador o servidor durante la sesión y se descargará directamente.

No se conservará una copia en la aplicación.

---

## 24. Métricas de éxito

Aunque no exista historial interno, las métricas podrán revisarse mediante el informe descargado de cada sesión.

### 24.1 Métricas operativas

- Porcentaje de expediciones con coincidencia segura.
- Porcentaje de expediciones ambiguas.
- Porcentaje de pedidos no encontrados.
- Porcentaje de cumplimientos exitosos.
- Número de pedidos ya preparados detectados.
- Número de seguimientos duplicados evitados.
- Número de correos duplicados evitados.
- Tiempo de procesamiento.
- Errores por ejecución.

### 24.2 Objetivos iniciales

| Métrica | Objetivo |
|---|---:|
| Pedidos incorrectamente completados | 0 |
| Cumplimientos duplicados | 0 |
| Correos duplicados | 0 |
| Coincidencias seguras procesadas correctamente | ≥ 98 % |
| Operaciones fallidas que bloquean todo el lote | 0 |
| Revalidaciones antes de cumplir | 100 % |

---

## 25. Criterios de aceptación

### AC-001

Dado un CSV con 69 filas y 17 filas `Solicitada`, cuando se analiza el archivo, la aplicación muestra 17 expediciones candidatas antes de deduplicar.

### AC-002

Dada una agencia `304`, cuando se normaliza, el resultado es `0304`.

### AC-003

Dado un código postal `8019`, cuando se normaliza, el resultado es `08019`.

### AC-004

Dado el albarán `14910300`, cuando se construye el seguimiento, la URL contiene:

```text
numero_albaran=14910300
```

### AC-005

Dada una referencia truncada que coincide con el inicio del nombre de envío, el mismo código postal y una fecha compatible, cuando solo existe un pedido no preparado, el resultado es `MATCHED_SAFE`.

### AC-006

Dado un nombre coincidente y un código postal diferente, la expedición no se considera coincidencia segura.

### AC-007

Dados dos pedidos compatibles, el resultado es `AMBIGUOUS` y ninguno se selecciona automáticamente.

### AC-008

Dado un pedido ya preparado, la aplicación no crea ningún cumplimiento adicional.

### AC-009

Dado un pedido ya preparado, la aplicación no solicita a Shopify el envío de una nueva notificación.

### AC-010

Dado un pedido con el mismo albarán ya asignado, la aplicación lo marca como `TRACKING_EXISTS`.

### AC-011

Dado un CSV procesado anteriormente, cuando se vuelve a subir, los pedidos ya preparados se omiten.

### AC-012

Dado que se suben varios CSV durante el mismo día, un pedido completado desde el primer archivo no vuelve a modificarse desde los siguientes.

### AC-013

Dadas cinco coincidencias seleccionadas, cuando una falla, las otras cuatro continúan procesándose.

### AC-014

Dado un cumplimiento exitoso, el pedido de Shopify contiene:

- Transportista `NACEX`.
- Número de albarán.
- URL de seguimiento.
- Estado preparado.

### AC-015

Dado que la opción de notificación está activada, cuando se crea un cumplimiento nuevo, Shopify recibe la instrucción de notificar al cliente.

### AC-016

Dado que la opción de notificación está desactivada, cuando se crea el cumplimiento, no se solicita el envío de la notificación.

### AC-017

Dado que un pedido cambia a preparado entre el análisis y la ejecución, la revalidación lo detecta y no se crea un segundo cumplimiento.

### AC-018

Después de la ejecución, el usuario puede descargar un informe CSV.

### AC-019

Al cerrar o recargar la aplicación, no existe obligación de recuperar la sesión anterior.

### AC-020

La aplicación funciona sin una base de datos propia.

---

## 26. Casos de prueba principales

### Caso 1 — Coincidencia correcta

```text
Referencia NACEX: Sonia María Torralbo
CP NACEX: 23770
Pedido Shopify: Sonia María Torralbo
CP Shopify: 23770
Estado: no preparado
```

Resultado esperado:

```text
MATCHED_SAFE
```

### Caso 2 — Nombre truncado

```text
Referencia NACEX: Paula Robles Rodrigu
Pedido Shopify: Paula Robles Rodríguez
CP de ambos: 08105
```

Resultado esperado:

```text
MATCHED_SAFE
```

### Caso 3 — Código postal distinto

```text
Referencia NACEX: Paula Robles Rodrigu
CP NACEX: 08105
CP Shopify: 08015
```

Resultado esperado:

```text
NO_MATCH
```

### Caso 4 — Dos pedidos del mismo cliente

```text
Dos pedidos no preparados
Mismo nombre
Mismo código postal
Fechas compatibles
```

Resultado esperado:

```text
AMBIGUOUS
```

### Caso 5 — Pedido ya preparado

Resultado esperado:

```text
ALREADY_FULFILLED
```

No se crea cumplimiento.

No se envía correo.

### Caso 6 — CSV repetido

El mismo CSV se procesa por segunda vez.

Resultado esperado para los pedidos ya completados:

```text
ALREADY_FULFILLED
```

o:

```text
TRACKING_EXISTS
```

### Caso 7 — Estado no permitido

```text
Estado: En tránsito
```

Resultado esperado:

```text
IGNORED_STATUS
```

### Caso 8 — Código postal sin cero

```text
CSV: 3400
Shopify: 03400
```

Resultado esperado:

```text
Coincidencia de código postal
```

### Caso 9 — Pedido completado entre análisis y ejecución

1. La app analiza el CSV.
2. El pedido aparece como pendiente.
3. Otro usuario prepara el pedido en Shopify.
4. La app intenta ejecutar.

Resultado esperado:

```text
SKIPPED_AFTER_REVALIDATION
```

No se crea cumplimiento.

No se envía correo.

### Caso 10 — Mismo cliente con pedido nuevo

1. Existe un CSV antiguo de Ana García.
2. El pedido original ya está preparado.
3. Ana realiza un nuevo pedido con el mismo CP.
4. Se vuelve a subir el CSV antiguo.

Resultado esperado:

- La aplicación utiliza la fecha para detectar que el nuevo pedido no corresponde.
- La fila se marca como `NO_MATCH` o `AMBIGUOUS`.
- El pedido nuevo no se completa.

---

## 27. Estrategia de lanzamiento

### Fase 1 — Modo análisis

- Se importa el CSV.
- Se buscan pedidos.
- Se muestran coincidencias.
- No se modifica Shopify.

Objetivo:

Validar el algoritmo de matching con datos reales.

### Fase 2 — Ejecución manual

- El usuario revisa los resultados.
- Selecciona coincidencias seguras.
- Confirma la ejecución.
- La aplicación completa los pedidos.
- Se descarga un informe.

### Fase 3 — Referencia con número de pedido

La referencia de NACEX pasará a contener el número de pedido de Shopify.

Ejemplo:

```text
Referencia NACEX: 58321
Pedido Shopify: #58321
```

La búsqueda por nombre, CP y fecha quedará como validación adicional.

### Fase 4 — Automatización adicional

Opciones futuras:

- Importar archivos desde una carpeta.
- Leer adjuntos de correo.
- Descargar automáticamente el CSV de NACEX.
- Completar coincidencias exactas sin intervención.
- Notificar únicamente excepciones.
- Añadir una BBDD solo si en el futuro se requiere historial persistente.

La base de datos no forma parte de la arquitectura inicial y no será necesaria mientras Shopify pueda actuar como fuente de verdad.

---

## 28. Evolución futura del matching

Cuando la referencia incluya el número de pedido, la prioridad será:

1. Número de pedido exacto.
2. Estado pendiente de preparación.
3. Código postal como verificación.
4. Nombre como comprobación adicional.
5. Fecha como control de seguridad.

Regla futura:

```text
Referencia NACEX = número de pedido Shopify
Y
Pedido pendiente de preparar
Y
Albarán no registrado
```

Esto reducirá:

- Coincidencias ambiguas.
- Errores por nombres truncados.
- Problemas con tildes.
- Problemas con clientes repetidos.
- Consultas innecesarias.
- Riesgo de asociar un CSV antiguo a un pedido nuevo.

---

## 29. Decisiones adoptadas para el MVP

| Decisión | Valor |
|---|---|
| Origen de datos | CSV manual |
| Estado procesado | `Solicitada` |
| Base de datos | No |
| Fuente de verdad | Shopify |
| Historial persistente | No |
| Informe descargable | Sí |
| Identificador en sesión | Agencia + albarán |
| Matching principal | Nombre de envío + CP + fecha |
| Referencias truncadas | Coincidencia por inicio |
| Similitud aproximada automática | No |
| Coincidencias ambiguas | Revisión manual |
| Pedidos parciales | Revisión manual |
| Varias ubicaciones | Revisión manual |
| Notificación al cliente | Activada por defecto |
| Revalidación antes de cumplir | Obligatoria |
| Prevención de duplicados | Estado y tracking de Shopify |
| CSV repetidos | Permitidos |
| Múltiples CSV por día | Permitidos |
| Ejecución automática | No en el MVP |
| Preparación | Todos los artículos pendientes compatibles |

---

## 30. Definición de terminado

El MVP se considerará terminado cuando:

1. Sea posible cargar el CSV real de NACEX.
2. Se detecten correctamente las filas `Solicitada`.
3. Se eliminen duplicados dentro del mismo CSV.
4. Se normalicen agencia y código postal.
5. Se generen correctamente las URLs de seguimiento.
6. Se encuentren pedidos mediante nombre, código postal y fecha.
7. Los casos ambiguos no se procesen automáticamente.
8. El usuario pueda revisar las coincidencias.
9. Los pedidos seleccionados se revaliden antes de ser modificados.
10. Los pedidos pendientes se completen correctamente.
11. Shopify reciba el albarán y la URL.
12. Los pedidos ya preparados se omitan.
13. Los seguimientos ya existentes se omitan.
14. No se creen cumplimientos duplicados.
15. No se envíen correos duplicados.
16. Se puedan subir varios CSV durante el mismo día.
17. Se pueda volver a subir el mismo CSV sin duplicar acciones.
18. El fallo de una fila no detenga el resto.
19. Se pueda descargar un informe final.
20. La aplicación funcione sin base de datos propia.
21. El proceso haya sido probado primero en una tienda de desarrollo o con pedidos de prueba.

---

## 31. Resumen del flujo definitivo

```text
Subir CSV NACEX
        ↓
Validar archivo
        ↓
Filtrar Estado = Solicitada
        ↓
Deduplicar agencia + albarán
        ↓
Normalizar datos
        ↓
Construir URL NACEX
        ↓
Buscar pedido en Shopify
        ↓
Validar nombre + CP + fecha
        ↓
Consultar estado y tracking existentes
        ↓
Mostrar vista previa
        ↓
Confirmación del usuario
        ↓
Revalidar Shopify
        ↓
¿Pedido ya preparado o tracking existente?
        ├── Sí → Omitir y no enviar correo
        └── No → Crear fulfillment y notificar
        ↓
Mostrar resultados
        ↓
Descargar informe opcional
```
