'use strict';

// Lighthouse CI `puppeteerScript` of the storefront audit (setup/audit/lighthouserc.default.yaml).
// It runs once before the runs of each URL, in the browser Lighthouse then measures with. Lighthouse
// empties the HTTP cache and the origin storage before every run but keeps the cookies, so the session
// opened here lasts through the runs of an /account page, and a public page is measured signed out.
//
// The customer signs in through the sign-in form, as a visitor would: LIGHTHOUSE_CUSTOMER_EMAIL and
// LIGHTHOUSE_CUSTOMER_PASSWORD (the demo shop has test@thelia.net / thelia). They are never printed.

const ACCOUNT_PATH = '/account';

/**
 * @param {import('puppeteer').Browser} browser
 * @param {{url: string}} context
 */
module.exports = async (browser, { url }) => {
  const target = new URL(url);
  const page = await browser.newPage();
  try {
    if (!target.pathname.startsWith(ACCOUNT_PATH)) {
      const session = await page.createCDPSession();
      await session.send('Network.clearBrowserCookies');

      return;
    }

    await page.goto(new URL(ACCOUNT_PATH, target).href, { waitUntil: 'domcontentloaded' });
    if (new URL(page.url()).pathname === ACCOUNT_PATH) {
      return;
    }

    const email = process.env.LIGHTHOUSE_CUSTOMER_EMAIL;
    const password = process.env.LIGHTHOUSE_CUSTOMER_PASSWORD;
    if (!email || !password) {
      throw new Error(`${target.pathname} needs a signed-in customer: set LIGHTHOUSE_CUSTOMER_EMAIL and LIGHTHOUSE_CUSTOMER_PASSWORD`);
    }

    await page.goto(new URL('/customer/login', target).href, { waitUntil: 'domcontentloaded' });
    await page.type('#thelia_customer_login_email', email);
    await page.type('#thelia_customer_login_password', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('form[name="thelia_customer_login"] button[type="submit"]'),
    ]);

    const landed = new URL(page.url()).pathname;
    if (landed !== ACCOUNT_PATH) {
      throw new Error(`signing the customer in landed on ${landed} instead of ${ACCOUNT_PATH}`);
    }
  } finally {
    await page.close();
  }
};
