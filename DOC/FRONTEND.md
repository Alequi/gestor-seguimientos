# Frontend y diseño de interacción

## Estado

Norma aprobada para todo el frontend. La implementación todavía no ha comenzado.

## Fuente obligatoria

Todo desarrollo o revisión de interfaz debe utilizar la skill `$emil-design-eng`, ubicada actualmente en:

```text
C:\Users\aleja\.agents\skills\emil-design-eng\SKILL.md
```

La skill debe leerse completa al iniciar cada trabajo de frontend. Este documento resume las reglas aplicadas al proyecto, pero no sustituye a la skill. Si no está disponible, el desarrollo debe detenerse y comunicarse al usuario.

## Objetivo de experiencia

La interfaz debe transmitir seguridad operativa, claridad y rapidez. El usuario manejará acciones que pueden modificar pedidos y enviar notificaciones, por lo que el diseño debe:

- Hacer visible el estado real de cada expedición.
- Diferenciar claramente análisis, selección, confirmación, ejecución y resultado.
- Evitar acciones accidentales.
- Proporcionar feedback inmediato sin introducir lentitud artificial.
- Mantener los casos ambiguos y errores comprensibles y accionables.
- Dar prioridad a la legibilidad de la tabla y a la revisión segura frente a efectos decorativos.

## Criterio para animaciones

Antes de crear una animación se responderá, en orden:

1. ¿Debe animarse teniendo en cuenta su frecuencia de uso?
2. ¿Qué propósito concreto cumple?
3. ¿Qué easing corresponde a ese movimiento?
4. ¿Cuál es la duración mínima que mantiene la transición comprensible?

Propósitos válidos:

- Explicar una relación espacial.
- Indicar un cambio de estado.
- Confirmar que una interacción fue recibida.
- Evitar un cambio visual brusco.

No se añadirá movimiento únicamente por decoración en interacciones frecuentes. Las acciones iniciadas por teclado no se animarán.

## Movimiento y respuesta

- Las animaciones habituales de interfaz deben permanecer por debajo de 300 ms.
- Botones: feedback de pulsación entre 100 y 160 ms, con una escala sutil cercana a `0.97`.
- Tooltips y popovers pequeños: entre 125 y 200 ms.
- Desplegables: entre 150 y 250 ms.
- Modales y drawers: entre 200 y 500 ms solo cuando el contexto lo justifique.
- Entradas y salidas utilizarán normalmente una curva `ease-out` explícita.
- Los movimientos dentro de pantalla utilizarán `ease-in-out` cuando corresponda.
- Los cambios de color o hover utilizarán `ease`.
- El movimiento constante utilizará `linear`.
- No se utilizará `ease-in` para respuestas de interfaz.
- No se animará desde `scale(0)`; las entradas por escala partirán de `0.9` o superior junto con opacidad.
- No se utilizará `transition: all`; deben declararse las propiedades exactas.

Curvas base iniciales permitidas:

```css
--ease-out: cubic-bezier(0.23, 1, 0.32, 1);
--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
```

Los valores definitivos se validarán visualmente en el contexto de cada componente; no se aplicarán automáticamente a todo.

## Componentes

- Todo elemento presionable debe responder de inmediato a la pulsación.
- Los popovers deben usar un origen de transformación relacionado con su disparador.
- Los modales mantendrán el origen centrado.
- Los tooltips pueden tener retraso inicial, pero los tooltips adyacentes posteriores deben aparecer sin retrasos repetitivos cuando la implementación lo permita.
- Para UI interrumpible se prefieren transiciones CSS a keyframes.
- Se utilizarán springs únicamente en gestos o interacciones físicas que puedan interrumpirse y cuando exista una justificación expresa.
- Los estados de carga deben mejorar la percepción de velocidad sin ocultar el progreso real ni simular resultados.
- Las transiciones nunca impedirán seleccionar, cancelar, revisar o continuar una acción.

## Rendimiento

- Se animarán preferentemente `transform` y `opacity`.
- No se animarán `width`, `height`, `margin` o `padding` cuando exista una alternativa que evite layout y paint continuos.
- Para animaciones predeterminadas se priorizará CSS sobre JavaScript.
- Las animaciones programáticas deben utilizar WAAPI cuando aporte control sin requerir una dependencia adicional.
- No se añadirá una librería de movimiento sin una necesidad concreta y una decisión documentada.
- La tabla de hasta 1.000 filas no debe degradarse por animaciones por fila, observers innecesarios ni recálculos globales.

## Accesibilidad

- Debe respetarse `prefers-reduced-motion`.
- En modo de movimiento reducido se eliminarán desplazamientos y escalados prescindibles, manteniendo transiciones discretas de opacidad o color que ayuden a comprender el estado.
- Los hover animados se limitarán mediante:

```css
@media (hover: hover) and (pointer: fine) {
  /* Estados hover reales. */
}
```

- El estado no dependerá únicamente del color o de una animación.
- El foco, la navegación por teclado y los anuncios accesibles deben conservarse durante cambios de estado y modales.

## Verificación de interfaz

Cada funcionalidad frontend deberá comprobar, según corresponda:

- Estado normal, hover, active, focus, disabled, loading, success y error.
- Navegación mediante teclado.
- Movimiento reducido.
- Interacciones táctiles cuando existan.
- Vista móvil y escritorio.
- Animaciones a velocidad reducida o fotograma a fotograma cuando sea necesario.
- Rendimiento durante carga, filtrado y ejecución de lotes.

Las revisiones de código o movimiento deben presentar los hallazgos en esta tabla:

| Before | After | Why |
|---|---|---|
| Comportamiento actual | Cambio propuesto | Motivo funcional o de calidad |

## Decisiones pendientes

- Sistema visual: colores, tipografía, escala de espaciado y densidad de tabla.
- Librería de componentes, si se utilizará alguna.
- Estrategia exacta para toasts, modales, popovers y tooltips.
- Necesidad real de una librería de movimiento.

No se elegirán estas opciones hasta que el usuario las confirme o solicite una propuesta específica.
