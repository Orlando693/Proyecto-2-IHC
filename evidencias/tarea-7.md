# Tarea 7 · Mejora por feedback y funcionalidad del caso (UniSport)

## Feedback recibido

- **Enlace:** [feedback de Guzman Montalvan Marcos Lehonti (Presupuesto Estudiantil)](https://github.com/MarcosLehonti/PROYECTO2-IHC/blob/main/docs/clase-19-feedback-a-otr-proyecto-/Guzman-Montalvan/feedback.md)
- **Flujo evaluado:** buscar un partido con cupo e inscribirse.
- **Problema principal señalado:** en "Explorar partidos" aparecían mezclados partidos con fecha ya vencida. Confundía sobre si todavía era posible unirse y obligaba a filtrar a mano.

## 1. Mejora por feedback

**Commit:** [`625e542` fix: ocultar partidos vencidos en Explorar](https://github.com/Lorgio09/proyecto2_ihc/commit/625e54208a797020e007d25ad2261cf0f87a0fe4)

| | |
|---|---|
| **Antes** | Explorar listaba todos los partidos de otros organizadores, incluso los de fecha pasada. Al intentar unirse a uno vencido, el backend lo rechazaba (`FECHA_PASADA`). |
| **Cambio** | `listar_partidos_publicos` ([service.py](../../backend/app/modules/partidos/service.py)) solo devuelve partidos con fecha posterior al momento actual. |
| **Por qué** | Un partido vencido no admite inscripciones; mostrarlo solo agrega ruido y pasos extra (heurística de prevención de errores y carga cognitiva). |

**Evidencia:** prueba nueva que crea un partido de mañana y otro de ayer, y comprueba que Explorar solo devuelve el de mañana.

```
tests/test_partidos_uniones.py::test_explorar_no_muestra_partidos_vencidos PASSED
```

No se aplicaron las otras sugerencias del feedback (filtro "solo con cupos" activado por defecto, colores en las tarjetas, renombrar la sección). El filtro por defecto ocultaría los partidos llenos, que ahora aceptan suplentes.

## 2. Funcionalidad del caso: reprogramar un partido y reconfirmar el equipo

**Commit:** [`6d02eca` feat: reprogramar partido con reconfirmación del equipo](https://github.com/Lorgio09/proyecto2_ihc/commit/6d02ecae7977a115114d4f13394c1f2216faa384)

### Reglas implementadas

- El organizador cambia fecha, hora o lugar desde **Mis partidos → Reprogramar**, con un motivo obligatorio.
- Los **titulares** deben reconfirmar; mientras no responden **conservan su plaza**. Los suplentes no tienen que responder.
- Cada titular, en **Explorar**, elige **Sigo jugando** o **Me retiro**.
- Si un titular se retira, **asciende el primer suplente** (por orden de llegada).
- Todos ven los datos nuevos, el motivo y quién falta responder ("Esperando respuesta de: …").
- Mientras haya respuestas pendientes **no se puede reprogramar otra vez**, ni cambiar fecha o lugar desde Editar.

### Cambios

| Capa | Cambio |
|---|---|
| Base de datos | Migración `b5e9c2d4f7a1`: `partidos.motivo_reprogramacion` y `partido_participantes.pendiente_confirmar`. |
| API | `PATCH /api/partidos/{id}/reprogramar` (organizador) y `POST /api/partidos/{id}/reconfirmar` (titular). Retirarse usa el `DELETE /api/partidos/{id}/salir` existente, que sube al suplente. Las respuestas agregan `motivo_reprogramacion` y `pendientes_confirmar`. |
| App | Diálogo `reprogramar_partido.dart`, botón **Reprogramar** en Mis partidos, y **Sigo jugando / Me retiro** en Explorar. |

### Evidencia

**Prueba de la regla pedida** ("un titular acepta y otro se retira; asciende el primer suplente sin exceder el cupo ni duplicar inscripciones") y el resto de casos:

```
test_reprogramar_cambia_datos_y_pide_reconfirmar_a_los_titulares     PASSED
test_un_titular_acepta_otro_se_retira_y_asciende_el_primer_suplente  PASSED
test_no_se_puede_reprogramar_otra_vez_con_respuestas_pendientes      PASSED
test_editar_fecha_tambien_se_bloquea_con_respuestas_pendientes       PASSED
test_reprogramar_sin_cambios_o_con_fecha_pasada_se_rechaza           PASSED
test_reconfirmar_sin_reprogramacion_pendiente_se_rechaza             PASSED
```

**Recorrido contra el backend real** con los datos semilla (partido "Duelo de dos", 2 cupos):

```
antes:        titulares=[Jugador Demo, Segundo Jugador]  suplentes=[Tercer Jugador]  pendientes=[]
reprogramar:  200  → lugar=Coliseo, motivo="Cancha en mantenimiento"
después:      titulares=[Jugador Demo, Segundo Jugador]  pendientes=[Jugador Demo, Segundo Jugador]
2da reprog.:  409 REPROGRAMACION_PENDIENTE
Jugador Demo acepta (200) · Segundo Jugador se retira (200)
final:        titulares=[Jugador Demo, Tercer Jugador]   suplentes=[]  pendientes=[]
```

Backend: 41 de 41 pruebas pasan.

### Decisiones

- El suplente que sube no necesita reconfirmar: entra viendo ya los datos nuevos.
- Un partido sin cambios reales (misma fecha y lugar) no se puede reprogramar (`SIN_CAMBIOS`).
- La captura de referencia `test/goldens/mis_partidos.png` cambia por el botón nuevo y debe regenerarse (`flutter test --update-goldens test/visual_test.dart`).
