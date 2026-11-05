import { test, expect } from '@playwright/test';

test('un usuario puede crear un plan y verlo en Mis planes', async ({ page }) => {
  const nombrePlan = `Plan E2E ${Date.now()}`;

  await page.goto('/login');
  await page.locator('#correo').fill('usuario1@planazo.com');
  await page.locator('#clave').fill('Planazo123!');
  await page.getByRole('button', { name: 'Entrar' }).click();

  await page.waitForURL(/\/mis-planes$/, { timeout: 60000 });
  await expect(page.getByRole('heading', { name: 'Nuevo plan' })).toBeVisible();

  await page.locator('#nombre').fill(nombrePlan);
  await page.locator('#fecha').fill('31 de diciembre');
  await page.getByRole('button', { name: 'Crear plan' }).click();

  await expect(page.locator('#lista')).toContainText(nombrePlan);
});

test('el organizador puede agregar opciones y votar después de crear un plan', async ({ page }) => {
  const nombrePlan = `Opciones E2E ${Date.now()}`;

  await page.goto('/login');
  await page.locator('#correo').fill('usuario1@planazo.com');
  await page.locator('#clave').fill('Planazo123!');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await page.waitForURL(/\/mis-planes$/, { timeout: 60000 });

  await page.locator('#nombre').fill(nombrePlan);
  await page.locator('#fecha').fill('31 de diciembre');
  await page.getByRole('button', { name: 'Crear plan' }).click();

  const plan = page.locator('#lista > .plan').filter({ hasText: nombrePlan });
  await expect(plan.getByRole('button', { name: 'Agregar opción' })).toBeVisible();

  for (const opcion of ['Sábado', 'Domingo']) {
    await plan.getByLabel('Tipo de opción').selectOption('fecha');
    await plan.locator('input[placeholder="Ejemplo: sábado 10 de octubre"]').fill(opcion);
    await plan.getByRole('button', { name: 'Agregar opción' }).click();
    await expect(plan).toContainText(`Fecha: ${opcion}`);
  }

  const opciones = plan.locator('.propuesta');
  await opciones.nth(0).getByRole('button', { name: 'Votar' }).click();
  await opciones.nth(1).getByRole('button', { name: 'Votar' }).click();
  await expect(plan).toContainText('Opciones y votación');

  await page.reload();
  await expect(page.locator('#lista > .plan').filter({ hasText: nombrePlan })).toContainText('Fecha: Sábado');
  await expect(page.locator('#lista > .plan').filter({ hasText: nombrePlan })).toContainText('Fecha: Domingo');
});
