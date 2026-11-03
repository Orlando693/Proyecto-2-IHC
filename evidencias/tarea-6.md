# Planazo - Varios votos

## Cambios realizados

La votación ahora permite que un participante vote varias propuestas del mismo plan, incluso si son del mismo tipo. Cada propuesta tiene los controles **Votar** y **Quitar voto**. El organizador puede usar **Cerrar votación**. Después del cierre se muestran los resultados, pero la aplicación ya no muestra controles para modificar votos y el servidor también rechaza esas solicitudes.

Se agregó una migración que conserva los datos existentes: reemplaza la unicidad de votos por `plan_id`, `user_id` y `tipo` por una unicidad de `proposal_id` y `user_id`. También agrega la marca `votacion_cerrada` al plan.

## Limitación antes del cambio

La tabla `votes` tenía la restricción única `plan_id`, `user_id`, `tipo`. Por eso un participante solo podía mantener un voto por cada tipo de propuesta. Al votar otra fecha, el backend reemplazaba el voto anterior mediante `updateOrCreate`.

## Matriz de pruebas

| Prueba | Antes | Después | Resultado |
| --- | --- | --- | --- |
| Iniciar sesión, crear un plan y verlo en Mis planes | La ejecución E2E previa falló: permaneció en `/login`. | Pasó en Chromium. | Correcto después del ajuste de espera y ejecución serial. |
| Votar dos propuestas de fecha | No era posible: el segundo voto reemplazaba al primero. | Las dos propuestas mostraron `1 voto(s)`. | Correcto. |
| Quitar un voto | No existía control ni endpoint. | La propuesta pasó de `1 voto(s)` a `0 voto(s)`. | Correcto. |
| Cambiar la selección | Solo se movía un voto por tipo. | Se agregó un voto a Sábado y se quitó el de Domingo; los conteos quedaron 1 y 0. | Correcto. |
| Persistencia al recargar | No había varios votos que comprobar. | Tras recargar, Sábado conservó `1 voto(s)` y el botón `Quitar voto`. | Correcto. |
| Cerrar votación | No existía. | El organizador cerró la votación y se conservaron los conteos. | Correcto. |
| Bloqueo después del cierre | No existía. | No se mostraron controles de voto y `POST /api/planes/votos/quitar` devolvió 400. | Correcto. |
| Accesibilidad básica | No se ejecutó antes. | `@axe-core/playwright` no devolvió violaciones en la pantalla de votación cerrada. | Correcto. |

## Resultados reales

Antes del cambio se ejecutó:

```bash
pnpm exec playwright test --project=chromium
```

Resultado: **0 aprobadas y 1 fallida**. La prueba existente no salió de `/login`; Playwright recibió `http://localhost:8000/login` en lugar de `/mis-planes`.

Después del cambio se ejecutó:

```bash
pnpm exec playwright test --project=chromium
```

Resultado: **2 aprobadas y 0 fallidas** en 2.5 minutos.

También se verificó el comportamiento del backend:

```bash
docker compose exec -T planazo php artisan test --filter=ParticipacionPlanTest
```

Resultado: **1 aprobada**, con 21 aserciones.

La migración aplicada sin reiniciar SQLite fue:

```bash
docker compose exec -T planazo php artisan migrate --force
```

## Evidencia

La prueba E2E guarda una captura real después del cierre en:

`evidencias/tarea-6-votacion-cerrada.png`

La prueba recarga la sesión del participante antes y después del cierre. Comprueba que el voto de Sábado sigue en `1 voto(s)`, que no aparecen botones **Votar** ni **Quitar voto**, y que el servidor rechaza el intento real de quitar el voto con estado HTTP 400.
