import { test, expect } from '@playwright/test';

test('un usuario puede crear un plan y verlo en Mis planes', async ({ page }) => {
  const nombrePlan = `Plan E2E ${Date.now()}`;

  await page.goto('/login');
  await page.locator('#correo').fill('usuario1@planazo.com');
  await page.locator('#clave').fill('Planazo123!');
  await page.getByRole('button', { name: 'Entrar' }).click();

  await expect(page).toHaveURL(/\/mis-planes$/);
  await expect(page.getByRole('heading', { name: 'Nuevo plan' })).toBeVisible();

  await page.locator('#nombre').fill(nombrePlan);
  await page.locator('#fecha').fill('31 de diciembre');
  await page.getByRole('button', { name: 'Crear plan' }).click();

  await expect(page.locator('#lista')).toContainText(nombrePlan);
});
