import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues } from '../../helpers/qa';

/**
 * QA sanity — validates that the helpers themselves work correctly.
 * Logs in to the BO, navigates to /admin/, attaches IssueCollector,
 * runs sweepScreen (tabs + modals), and fails with a formatted report
 * if any issues are collected.
 */
test('qa-helpers: sweepScreen on /admin/ dashboard produces no issues', async ({ page }) => {
  const collector = new IssueCollector(page);
  collector.attach();

  await loginAdmin(page);

  await page.goto('/admin/', { waitUntil: 'networkidle' });

  // allowDanger: the dashboard shows business alerts (e.g. "unpaid orders over 48h") from demo data.
  // These are expected .alert-danger items, not application errors.
  const issues = await sweepScreen(page, collector, { tabs: true, modals: true, allowDanger: true });

  expect(issues, formatIssues(issues)).toHaveLength(0);
});
