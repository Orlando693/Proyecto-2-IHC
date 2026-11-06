# Clase 20 - Mejora de navegación en Planazo

**Integrante:** Orlando Sebastian Moreno Cors

## 1. Problema identificado

En Planazo, las opciones para cerrar una votación y confirmar al ganador no estaban suficientemente agrupadas, lo que podía dificultar encontrarlas.

## 2. Propuesta de mejora

Se decidió agrupar estas acciones dentro de una sección llamada **Cierre y resultado**, con un texto que explique para qué sirve.

**Dibujo de la propuesta:**

```text
PLANAZO
┌────────────────────────────┐
│ Opciones y votación        │
│ Agregar opción             │
│ Votar                      │
├────────────────────────────┤
│ CIERRE Y RESULTADO         │
│                            │
│ 1. Cerrar votación         │
│ 2. Confirmar ganador       │
│                            │
│ Resultado de la votación   │
└────────────────────────────┘
```

## 3. Implementación

Se reorganizaron las acciones de cierre y confirmación dentro de la pantalla de planes, sin modificar las reglas de votación ni la base de datos.

**Archivo modificado:** `apps/web/paginas/planes.html`

**Antes:** [Agregar captura del estado anterior]

**Después:** [Agregar captura del estado nuevo]

## 4. Prueba con otra persona

- **Evaluador:** Sebastian Claure
- **Grupo:** Mascota al dia
- **Tarea:** Encontrar cómo cerrar una votación y confirmar al ganador.
- **Resultado:**  No lo encontró
- **Dónde dudó:** en la interfaz que no se entendia bien el flujo
- **Ajustes posteriores:** Justamente para entende se corrigio en el aspecto de la duda

## 5. Commit

b63865c54c28f4fd960c44139aa66ba56b3c547e

## 6. Conclusión

La prueba permitió observar que la nueva organización de la sección **“Cierre y resultado”** facilita encontrar las acciones para cerrar la votación y confirmar al ganador. Al agrupar estas opciones y agregar una breve explicación, la navegación resulta más clara e intuitiva, sin necesidad de modificar el funcionamiento de la aplicación.