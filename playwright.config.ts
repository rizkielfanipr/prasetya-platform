import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: { baseURL: 'http://127.0.0.1:8123', trace: 'retain-on-failure', screenshot: 'only-on-failure', locale: 'id-ID', timezoneId: 'Asia/Jakarta' },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } }],
    webServer: {
        command: 'php artisan serve --env=e2e --host=127.0.0.1 --port=8123 --no-reload',
        url: 'http://127.0.0.1:8123/api/v1/health',
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
