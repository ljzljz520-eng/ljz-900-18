// @ts-check
// 运行前请先执行: npx playwright install chromium
// 并确保应用已启动（如 docker compose up -d 后访问 http://localhost:3000）
import { test, expect } from '@playwright/test'

test.describe('登录与退出', () => {
  test('访问根路径应显示登录页或跳转到登录', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/(login|admin)$/)
    const url = new URL(page.url())
    if (url.pathname === '/login') {
      await expect(page.getByPlaceholder('用户名')).toBeVisible()
    }
  })

  test('登录后点击退出应跳转到登录页', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByPlaceholder('用户名')).toBeVisible({ timeout: 10000 })

    await page.getByPlaceholder('用户名').fill('admin')
    await page.getByPlaceholder('密码').fill('admin123')
    await page.getByRole('button', { name: /登\s*录/ }).click()

    await expect(page).toHaveURL(/\/admin/, { timeout: 10000 })
    await expect(page.getByText('检查上传')).toBeVisible({ timeout: 5000 })

    // 点击用户区域打开下拉菜单
    await page.locator('.user-trigger').click()
    await expect(page.locator('.user-menu').first()).toBeVisible({ timeout: 3000 })

    // 点击退出登录（表单提交）
    await page.locator('form.user-menu-logout-form button[type="submit"]').click()

    await expect(page).toHaveURL(/\/login/, { timeout: 5000 })
    await expect(page.getByPlaceholder('用户名')).toBeVisible({ timeout: 3000 })
  })
})
