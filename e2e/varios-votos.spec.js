import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function iniciarSesion(page, correo) {
  await page.goto('/login');
  await page.locator('#correo').fill(correo);
  await page.locator('#clave').fill('Planazo123!');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await page.waitForURL(/\/mis-planes$/, { timeout: 60000 });
}

function tarjetaPlan(page, nombrePlan) {
  return page.locator('#lista > .plan').filter({ hasText: nombrePlan });
}

test('un participante puede cambiar varios votos y el organizador confirma el resultado', async ({ page, browser }) => {
  test.setTimeout(180000);
  const nombrePlan = `Votos E2E ${Date.now()}`;
  const participanteContexto = await browser.newContext();
  const participante = await participanteContexto.newPage();

  try {
    await iniciarSesion(page, 'usuario1@planazo.com');
    await page.locator('#nombre').fill(nombrePlan);
    await page.locator('#fecha').fill('31 de diciembre');
    await page.getByRole('button', { name: 'Crear plan' }).click();

    const planOrganizador = tarjetaPlan(page, nombrePlan);
    await expect(planOrganizador).toBeVisible();
    await planOrganizador.locator('input[type="email"]').fill('usuario2@planazo.com');
    await planOrganizador.getByRole('button', { name: 'Agregar participante' }).click();
    await expect(planOrganizador).toContainText('Usuario Dos');

    await iniciarSesion(participante, 'usuario2@planazo.com');
    const planParticipante = tarjetaPlan(participante, nombrePlan);
    await expect(planParticipante).toBeVisible();

    for (const propuesta of ['Sábado', 'Domingo']) {
      await planParticipante.getByLabel('Tipo de opción').selectOption('fecha');
      await planParticipante.locator('input[placeholder="Ejemplo: sábado 10 de octubre"]').fill(propuesta);
      await planParticipante.getByRole('button', { name: 'Agregar opción' }).click();
      await expect(planParticipante).toContainText(`Fecha: ${propuesta}`);
    }

    const sabado = planParticipante.locator('.propuesta').filter({ hasText: 'Fecha: Sábado' });
    const domingo = planParticipante.locator('.propuesta').filter({ hasText: 'Fecha: Domingo' });
    await sabado.getByRole('button', { name: 'Votar' }).click();
    await domingo.getByRole('button', { name: 'Votar' }).click();
    await expect(sabado).toContainText('1 voto(s)');
    await expect(domingo).toContainText('1 voto(s)');

    await sabado.getByRole('button', { name: 'Quitar voto' }).click();
    await expect(sabado).toContainText('0 voto(s)');
    await expect(domingo).toContainText('1 voto(s)');

    await sabado.getByRole('button', { name: 'Votar' }).click();
    await domingo.getByRole('button', { name: 'Quitar voto' }).click();
    await expect(sabado).toContainText('1 voto(s)');
    await expect(domingo).toContainText('0 voto(s)');
    const idPlan = await sabado.getByRole('button', { name: 'Quitar voto' }).getAttribute('data-plan');
    const idSabado = await sabado.getByRole('button', { name: 'Quitar voto' }).getAttribute('data-propuesta');

    await participante.reload();
    await expect(tarjetaPlan(participante, nombrePlan).locator('.propuesta').filter({ hasText: 'Fecha: Sábado' })).toContainText('1 voto(s)');
    await expect(tarjetaPlan(participante, nombrePlan).getByRole('button', { name: 'Quitar voto' })).toBeVisible();

    await page.reload();
    await tarjetaPlan(page, nombrePlan).getByRole('button', { name: 'Cerrar votación' }).click();
    await expect(tarjetaPlan(page, nombrePlan)).toContainText('Votación cerrada. Puedes consultar los resultados.');
    await expect(tarjetaPlan(page, nombrePlan)).toContainText('Opción con más votos: Sábado');
    await tarjetaPlan(page, nombrePlan).getByRole('button', { name: 'Confirmar resultado' }).click();
    await expect(tarjetaPlan(page, nombrePlan)).toContainText('Resultado confirmado: Fecha: Sábado');

    await participante.reload();
    const planCerrado = tarjetaPlan(participante, nombrePlan);
    await expect(planCerrado).toContainText('Votación cerrada. Puedes consultar los resultados.');
    await expect(planCerrado).toContainText('Fecha: Sábado · 1 voto(s)');
    await expect(planCerrado).toContainText('Resultado confirmado: Fecha: Sábado');
    await expect(planCerrado.getByRole('button', { name: /Votar|Quitar voto/ })).toHaveCount(0);

    const resultado = await participante.evaluate(async ({ id, propuestaId }) => {
      const respuesta = await fetch('/api/planes/votos/quitar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, propuesta_id: propuestaId }),
      });
      return respuesta.status;
    }, {
      id: idPlan,
      propuestaId: idSabado,
    });
    await expect(resultado).toBe(400);
    await participante.reload();
    await expect(tarjetaPlan(participante, nombrePlan)).toContainText('Fecha: Sábado · 1 voto(s)');

    const accesibilidad = await new AxeBuilder({ page: participante }).analyze();
    await expect(accesibilidad.violations).toEqual([]);
    await planCerrado.screenshot({ path: 'evidencias/tarea-6-votacion-cerrada.png' });
  } finally {
    await participanteContexto.close();
  }
});
