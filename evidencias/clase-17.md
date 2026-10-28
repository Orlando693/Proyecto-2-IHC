# Clase 17 · Análisis del proyecto recibido (Planazo)

## 1. Proyecto recibido

- **Repositorio original del grupo anterior:** https://github.com/DSaav22/Proyecto-2-IHC
- **Mi fork:** (https://github.com/Orlando693/Proyecto-2-IHC)
- **Nombre del proyecto:** Planazo.
- **Tecnologías:** HTML, CSS y JavaScript en `apps/web`; PHP con Laravel en `apps/backend`; SQLite como base de datos.

La aplicación permite registrarse, iniciar sesión y crear, editar, confirmar o cancelar planes. El historial muestra los cambios realizados por el usuario autenticado.

## 2. Ejecución local

El README original explica cómo ejecutarlo con PHP y las extensiones SQLite. Para esta revisión se preparó además un `docker-compose.yml` en la raíz, que instala las dependencias de Laravel y arranca el servidor en Docker.

Desde la raíz del repositorio, con Docker Desktop encendido:

```powershell
docker compose up --build
```

Dirección local: `http://localhost:8000`.

Usuario de prueba documentado en el repositorio: `usuario1@planazo.com` / `Planazo123!`.

### Captura del comando

![alt text](comandos-1.png)

 tomar una captura de la terminal cuando el contenedor esté iniciado correctamente y guardarla como `evidencias/comandos.png`. No usar una captura del proceso con errores.

![Terminal con la aplicación iniciada](./ejecucion-comando.png)

### Captura de la aplicación

![alt text](ejecucionapp-1.png)

 abrir Planazo, iniciar sesión, mostrar la pantalla Mis planes y guardar una captura propia como `evidencias/ejecucionapp.png`.

![Planazo funcionando en el navegador](./ejecucion-app.png)

**Estado de verificación:** pendiente de añadir las capturas de una ejecución exitosa. No se afirma que haya funcionado hasta comprobarlo en esta máquina.

## 3. Recorrido real: pantalla → acción → regla → datos

Se analiza la acción **Cancelar plan**, desde la interfaz hasta su persistencia.

### Pantalla

**Archivo:** `apps/web/paginas/planes.html`.

En `/mis-planes`, el usuario ve los planes y sus estados (`borrador`, `confirmado` o `cancelado`). Si es el organizador y el plan no está cancelado, la página muestra el botón **Cancelar plan**. Al pulsarlo, aparece una confirmación con el nombre, fecha y estado del plan.

### Acción

**Archivos:** `apps/web/paginas/planes.html`, `apps/web/js/comun.js` y `apps/backend/routes/web.php`.

Después de aceptar la confirmación, la función JavaScript `cancelar(id)` envía el identificador del plan mediante:

```text
POST /api/planes/cancelar
{ "id": <id_del_plan> }
```

La ruta enlaza la petición con el método `cancelar` de `PlanController`.

### Regla de negocio

**Archivos:** `apps/backend/app/Http/Controllers/PlanController.php` y `apps/backend/app/ReglasPlan.php`.

El controlador comprueba que:

1. Exista una sesión iniciada.
2. Exista el plan solicitado.
3. La persona autenticada sea quien organiza ese plan.
4. El plan esté en estado `borrador` o `confirmado`; no se puede cancelar dos veces.

La clase `ReglasPlan` implementa la validación `puedeCancelar(...)`. Si se incumple una regla, el backend devuelve un mensaje de error y no realiza la cancelación.

### Datos

**Archivos:** `apps/backend/app/Models/Plan.php`, `apps/backend/app/Models/Cambio.php` y las migraciones de `apps/backend/database/migrations/`.

Si la operación es válida, `PlanController` cambia `estado` a `cancelado` y guarda el registro en la tabla `plans`. Además, crea un registro en la tabla `cambios`, con el identificador del usuario y del plan, el nombre, la acción `cancelado` y el resultado.

El método `cambios()` del controlador consulta el historial del usuario autenticado, ordenado desde los cambios más recientes. La pantalla lo solicita mediante `GET /api/cambios` y lo muestra al pulsar **Historial**.

**Resultado esperado para comprobarlo:** crear un plan de prueba, cancelarlo, observar el estado `Cancelado`, abrir el historial y comprobar que se registró la acción. Se debe recargar la página para verificar que el estado permanece guardado.

## 4. Impacto para implementar la Tarea 5

**PENDIENTE: incorporar aquí el enunciado exacto de la Tarea 5 asignada.** La diapositiva de Clase 17 pide analizar su impacto, pero no especifica qué funcionalidad debe añadirse a Planazo. No corresponde inventar ese requisito.

Como punto de partida, según la funcionalidad solicitada, estos serían los archivos a revisar:

| Capa | Archivo real | Posible modificación |
| --- | --- | --- |
| Pantalla e interacción | `apps/web/paginas/planes.html` | Agregar o modificar controles y mensajes visibles. |
| Comunicación HTTP | `apps/web/js/comun.js` y `apps/backend/routes/web.php` | Enviar la acción y registrar la ruta necesaria. |
| Reglas y controlador | `apps/backend/app/ReglasPlan.php` y `apps/backend/app/Http/Controllers/PlanController.php` | Validar permisos, estados y comportamiento. |
| Persistencia | `apps/backend/app/Models/` y `apps/backend/database/migrations/` | Guardar nuevas propiedades o relaciones si hacen falta. |
| Pruebas | `apps/backend/tests/` | Verificar casos válidos, errores y persistencia. |

**Completar antes de entregar:** describir la Tarea 5 concreta, identificar qué existe hoy y qué debe cambiar, y eliminar todas las marcas `PENDIENTE` de este archivo.
