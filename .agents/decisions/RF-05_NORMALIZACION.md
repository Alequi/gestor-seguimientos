# RF-05 — Normalización de candidatas

## Estado

Decisión funcional confirmada por el usuario el 3 de agosto de 2026.

## Decisión

- La agencia se completa con ceros a la izquierda hasta cuatro dígitos.
- El código postal se completa con ceros a la izquierda hasta cinco dígitos.
- Agencia y código postal se consideran entradas numéricas válidas; RF-05 no añade una clasificación de errores para valores vacíos, no numéricos o demasiado largos porque esos casos no existirán en los CSV de entrada.
- La referencia normalizada se convierte a minúsculas y pierde las tildes, pero conserva la letra `ñ` como `ñ`.
- Los apóstrofes, guiones y demás signos se convierten en espacios. Después se colapsan los espacios consecutivos y se eliminan los exteriores.
- La referencia original se conserva sin cambios junto a su representación normalizada.

## Ejemplos

```text
304                 → 0304
8019                → 08019
PEÑA-MUÑOZ, Álvarez → peña muñoz alvarez
```
