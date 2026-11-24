import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests',
    snapshotPathTemplate: '{testDir}/snapshots/{arg}{ext}',
    workers: 1,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    use: {
        baseURL: process.env.BASE_URL ?? 'http://php-e2e',
        testIdAttribute: 'data-test',
        locale: 'en-GB',
        timezoneId: 'Europe/Paris',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /\.setup\.js/ },
        { name: 'desktop', dependencies: ['setup'], use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } }, testIgnore: process.env.SHOTS ? [] : /shots\.spec\.js/ },
    ],
});
