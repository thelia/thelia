import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { loginAdmin } from '../../helpers/admin';

/**
 * The product videos from the merchant's paste to the shopper's click: each platform
 * the shop accepts, a file the shop hosts itself, the refusals, the poster, the
 * visibility and the order in the gallery. Every case is walked in the back office
 * (BO Twig) and read back on the Flexy product page.
 */

type Platform = {
  name: string;
  /** The address a merchant pastes, in one of the forms the platform hands out. */
  pasted: string;
  /** What the core keeps of it, and what the frame address ends with. */
  identifier: string;
  /** The only origin the player frame may point at. */
  frameOrigin: string;
  badge: RegExp;
};

const PLATFORMS: Platform[] = [
  {
    name: 'YouTube',
    pasted: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
    identifier: 'dQw4w9WgXcQ',
    frameOrigin: 'https://www.youtube-nocookie.com',
    badge: /YouTube/i,
  },
  {
    name: 'Vimeo',
    pasted: 'https://vimeo.com/76979871',
    identifier: '76979871',
    frameOrigin: 'https://player.vimeo.com',
    badge: /Vimeo/i,
  },
  {
    name: 'Dailymotion',
    pasted: 'https://www.dailymotion.com/video/x97z2zc',
    identifier: 'x97z2zc',
    frameOrigin: 'https://www.dailymotion.com',
    badge: /Dailymotion/i,
  },
];

const UNKNOWN_URL = 'https://example.com/video/1';

type Shop = { productId: string; imageIds: string[]; frontUrl: string };

/**
 * The dev database is not fixed: no id is written down here. The first visible product
 * whose images tab has at least two images is the one every test works on — two, so that
 * a poster other than the first image can be chosen.
 */
async function findProduct(page: Page): Promise<Shop> {
  await page.goto('/admin/products');
  const ids = await page.$$eval('a[href*="product_id="]', (links) =>
    Array.from(
      new Set(
        links
          .map((link) => /product_id=(\d+)/.exec(link.getAttribute('href') ?? '')?.[1])
          .filter((id): id is string => Boolean(id)),
      ),
    ),
  );
  expect(ids.length, 'the shop must have at least one product').toBeGreaterThan(0);

  for (const productId of ids) {
    const response = await page.request.get(`/admin/image/type/product/${productId}/list-ajax`);
    if (!response.ok()) {
      continue;
    }
    const html = await response.text();
    // The theme the test job installs may predate the videos: the grid then carries
    // no reorder address, and nothing below can be judged.
    test.skip(!html.includes('data-bo-file-list-reorder-url-value'), 'the installed back-office theme predates the product videos');
    const imageIds = Array.from(html.matchAll(/data-testid="bo-file-alt-input-(\d+)"/g)).map((m) => m[1]);
    if (imageIds.length < 2) {
      continue;
    }
    const frontUrl = await frontUrlOf(page, productId);
    if (frontUrl === null) {
      continue;
    }

    return { productId, imageIds, frontUrl };
  }

  throw new Error('no visible product with two images found in this shop');
}

/** The address of the product on the front, read from its SEO tab; null when the page is not served. */
async function frontUrlOf(page: Page, productId: string): Promise<string | null> {
  await page.goto(`/admin/products/update?product_id=${productId}&current_tab=seo`);
  const slug = await page.locator('#tab-seo input[name$="[url]"]').first().inputValue();
  if (!slug) {
    return null;
  }
  const url = `/${slug}`;
  const response = await page.request.get(url);

  return response.ok() ? url : null;
}

async function openImagesTab(page: Page, productId: string): Promise<void> {
  await page.goto(`/admin/products/update?product_id=${productId}&current_tab=images`);
  await expect(page.getByTestId('bo-video-panel')).toBeVisible();
}

/** Posts the add form and returns the status the server answered. */
async function addVideo(page: Page, productId: string, source: { url?: string; file?: string }, title: string): Promise<number> {
  await openImagesTab(page, productId);
  if (source.url) {
    await page.getByTestId('bo-video-url').fill(source.url);
  }
  if (source.file) {
    await page.getByTestId('bo-video-file').setInputFiles(source.file);
  }
  await page.getByTestId('bo-video-title').fill(title);
  await page.getByTestId('bo-video-alt').fill(`${title}, alternative text`);
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/save-ajax') && r.request().method() === 'POST'),
    page.getByTestId('bo-video-add-submit').click(),
  ]);

  return response.status();
}

async function gridHtml(page: Page, productId: string): Promise<string> {
  const response = await page.request.get(`/admin/image/type/product/${productId}/list-ajax`);

  return response.text();
}

async function videoIdsOf(page: Page, productId: string): Promise<string[]> {
  return Array.from((await gridHtml(page, productId)).matchAll(/data-testid="bo-video-item-(\d+)"/g)).map((m) => m[1]);
}

async function deleteAllVideos(page: Page, productId: string): Promise<void> {
  for (const id of await videoIdsOf(page, productId)) {
    await openImagesTab(page, productId);
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByTestId(`bo-video-delete-${id}`).click();
    await expect(page.getByTestId(`bo-video-item-${id}`)).toHaveCount(0);
  }
}

async function gridToken(page: Page, productId: string): Promise<string> {
  await openImagesTab(page, productId);

  return (await page.locator('[data-controller="bo-file-list"]').first().getAttribute('data-bo-file-list-token-value')) ?? '';
}

/** Every card of the media grid, first card first, as the `type:id` entries the reorder takes. */
async function mediaOrderOf(page: Page, productId: string): Promise<string[]> {
  return Array.from((await gridHtml(page, productId)).matchAll(/data-file-id="(\d+)"\s+data-media-type="(\w+)"/g)).map((m) => `${m[2]}:${m[1]}`);
}

async function postOrder(page: Page, productId: string, order: string[], token: string) {
  const body = new URLSearchParams();
  order.forEach((entry) => body.append('order[]', entry));
  body.set('_token', token);

  return page.request.post(`/admin/product/${productId}/media/reorder`, {
    data: body.toString(),
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
  });
}

/**
 * What the browser complained about while a page was open: a Content Security Policy
 * refusal or a script error would go unnoticed otherwise, the poster being shown all the same.
 */
function watchConsole(page: Page): string[] {
  const problems: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      problems.push(message.text());
    }
  });
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));

  return problems;
}

/**
 * A real, playable WebM of a second or so, drawn on a canvas and recorded by the browser
 * itself: no binary fixture in the repository, and a file the upload policy has to accept.
 */
async function recordSampleVideo(page: Page): Promise<string> {
  await page.goto('about:blank');
  const base64 = await page.evaluate(async () => {
    const canvas = document.createElement('canvas');
    canvas.width = 160;
    canvas.height = 90;
    const context = canvas.getContext('2d')!;
    const recorder = new MediaRecorder(canvas.captureStream(15), { mimeType: 'video/webm' });
    const chunks: Blob[] = [];
    recorder.ondataavailable = (event) => chunks.push(event.data);
    const stopped = new Promise<void>((resolve) => {
      recorder.onstop = () => resolve();
    });
    recorder.start();
    for (let frame = 0; frame < 20; frame++) {
      context.fillStyle = `hsl(${frame * 18}, 80%, 50%)`;
      context.fillRect(0, 0, 160, 90);
      await new Promise((resolve) => setTimeout(resolve, 50));
    }
    recorder.stop();
    await stopped;
    const bytes = new Uint8Array(await new Blob(chunks, { type: 'video/webm' }).arrayBuffer());
    let binary = '';
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });

    return btoa(binary);
  });

  const file = path.join(os.tmpdir(), `thelia-sample-${Date.now()}.webm`);
  fs.writeFileSync(file, Buffer.from(base64, 'base64'));

  return file;
}

/** Opens the product page and returns its players, without having clicked any. */
async function openFront(page: Page, frontUrl: string) {
  await page.goto(frontUrl);
  const players = page.locator('.VideoPlayer');
  await expect(page.locator('iframe.VideoPlayer-frame')).toHaveCount(0);
  await expect(page.locator('video.VideoPlayer-file')).toHaveCount(0);

  return players;
}

/** The thumbnail of the first visual that is not a video. */
function firstImageThumbnail(page: Page) {
  return page
    .locator('.ProductGallery-list li')
    .filter({ hasNot: page.locator('.ProductGallery-videoBadge') })
    .first()
    .locator('button')
    .first();
}

/**
 * Brings the first video slide of the gallery on screen, the way a shopper does: through
 * its thumbnail. A slide the carousel keeps off screen is hidden from assistive technology
 * and takes no click, so the poster and the play button are only judged once shown.
 */
async function showVideoSlide(page: Page) {
  const thumbnail = page.locator('.ProductGallery-list li').filter({ has: page.locator('.ProductGallery-videoBadge') }).first();
  await expect(thumbnail).toHaveCount(1);
  // The carousel marks its active slide once mounted: a click before that goes nowhere.
  await expect(page.locator('.splide__slide.is-active').first()).toBeVisible();
  const player = page.locator('.splide__slide.is-active .VideoPlayer').first();
  await thumbnail.locator('button').first().click();
  try {
    await expect(player).toBeVisible({ timeout: 5000 });
  } catch {
    // The gallery controller may have been wired after the first click: ask once more.
    await thumbnail.locator('button').first().click();
    await expect(player).toBeVisible();
  }

  return player;
}

test.describe('Product videos — every source, from the paste to the click (BO Twig + Flexy)', () => {
  test.skip((process.env.BO_TEMPLATE ?? 'default') !== 'default-twig', 'BO Twig only.');

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  for (const platform of PLATFORMS) {
    test(`a ${platform.name} address is kept as its identifier, and played in a frame on the first click only`, async ({ page }) => {
      const { productId, frontUrl } = await findProduct(page);
      await deleteAllVideos(page, productId);
      const problems = watchConsole(page);

      try {
        expect(await addVideo(page, productId, { url: platform.pasted }, `${platform.name} demo`)).toBe(200);

        // The grid names the platform, and never shows the address that was pasted.
        const [videoId] = await videoIdsOf(page, productId);
        expect(videoId, 'the address must have produced a video').toBeTruthy();
        await openImagesTab(page, productId);
        await expect(page.getByTestId(`bo-video-provider-${videoId}`)).toContainText(platform.badge);
        const html = await gridHtml(page, productId);
        expect(html).not.toContain(platform.pasted);
        expect(html).not.toContain('watch?v=');
        // The poster is the first image of the product until one is chosen.
        await expect(page.getByTestId(`bo-video-thumb-${videoId}`).locator('img')).toBeVisible();

        // Front: a poster and a play button, nothing loaded from the platform yet.
        const players = await openFront(page, frontUrl);
        await expect(players).toHaveCount(1);
        const player = await showVideoSlide(page);
        const poster = player.locator('img.VideoPlayer-poster');
        await expect(poster).toBeVisible();
        expect(await poster.getAttribute('src')).toBeTruthy();
        const play = player.locator('button.VideoPlayer-play');
        await expect(play).toHaveAccessibleName(/\w/);

        // The click builds the frame, pointed at the platform's own player and nowhere else.
        await play.click();
        const frame = page.locator('iframe.VideoPlayer-frame');
        await expect(frame).toHaveCount(1);
        const src = (await frame.getAttribute('src')) ?? '';
        expect(src.startsWith(platform.frameOrigin + '/')).toBeTruthy();
        expect(src.endsWith('/' + platform.identifier)).toBeTruthy();
        expect(await frame.getAttribute('title')).toMatch(/\w/);
        await expect(frame).toBeVisible();

        expect(problems.filter((p) => /Content Security Policy|Refused to frame|pageerror/.test(p))).toEqual([]);
      } finally {
        await deleteAllVideos(page, productId);
      }
    });
  }

  test('a hosted file is uploaded, published in the web space and played by the native player', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);
    const sample = await recordSampleVideo(page);
    const problems = watchConsole(page);

    try {
      expect(await addVideo(page, productId, { file: sample }, 'Hosted demo')).toBe(200);
      const [videoId] = await videoIdsOf(page, productId);
      expect(videoId, 'the upload must have produced a video').toBeTruthy();
      await openImagesTab(page, productId);
      await expect(page.getByTestId(`bo-video-provider-${videoId}`)).toBeVisible();

      const players = await openFront(page, frontUrl);
      await expect(players).toHaveCount(1);
      await (await showVideoSlide(page)).locator('button.VideoPlayer-play').click();

      const video = page.locator('video.VideoPlayer-file');
      await expect(video).toHaveCount(1);
      const fileUrl = (await video.getAttribute('src')) ?? '';
      expect(fileUrl).toMatch(/\/cache\/videos\//);
      // The file the shop serves is the one that was uploaded, with its real type.
      const served = await page.request.get(fileUrl);
      expect(served.status()).toBe(200);
      expect(served.headers()['content-type']).toMatch(/^video\/webm/);
      // And the browser can read it: metadata arrives, no media error.
      const outcome = await video.evaluate(
        (element: HTMLVideoElement) =>
          new Promise<string>((resolve) => {
            if (element.readyState >= 1) {
              resolve('ok');
            }
            element.addEventListener('loadedmetadata', () => resolve('ok'), { once: true });
            element.addEventListener('error', () => resolve(`error:${element.error?.code ?? '?'}`), { once: true });
            setTimeout(() => resolve(`timeout:readyState=${element.readyState}`), 10000);
          }),
      );
      expect(outcome).toBe('ok');
      expect(problems.filter((p) => /pageerror/.test(p))).toEqual([]);
    } finally {
      await deleteAllVideos(page, productId);
      fs.rmSync(sample, { force: true });
    }
  });

  test('an unknown address is refused with the accepted platforms, and a platform switched off is refused too', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);
    const vimeo = PLATFORMS.find((p) => p.name === 'Vimeo')!;

    const setVimeo = async (enabled: boolean) => {
      await page.goto('/admin/configuration/store');
      const box = page.getByTestId('config-store-video-providers').locator('input[type="checkbox"][value="vimeo"]');
      await expect(box).toHaveCount(1);
      await box.setChecked(enabled);
      await page.locator('form').filter({ has: box }).locator('button[type="submit"]').first().click();
      await page.goto('/admin/configuration/store');
      await expect(page.getByTestId('config-store-video-providers').locator('input[type="checkbox"][value="vimeo"]')).toBeChecked({ checked: enabled });
    };

    try {
      // Unknown platform: refused, and the merchant is told what the shop accepts.
      expect(await addVideo(page, productId, { url: UNKNOWN_URL }, 'Refused')).toBe(422);
      await expect(page.locator('#bo-video-add-errors')).toContainText(/YouTube/);
      await expect(page.locator('#bo-video-add-errors')).toContainText(/Vimeo/);
      await expect(page.locator('#bo-video-add-errors')).toContainText(/Dailymotion/);
      expect(await videoIdsOf(page, productId)).toEqual([]);

      // A Vimeo video added while Vimeo is on...
      expect(await addVideo(page, productId, { url: vimeo.pasted }, 'Vimeo demo')).toBe(200);
      const players = await openFront(page, frontUrl);
      await expect(players).toHaveCount(1);

      // ...is neither offered to the shopper nor accepted any more once Vimeo is off.
      await setVimeo(false);
      expect(await addVideo(page, productId, { url: vimeo.pasted }, 'Vimeo refused')).toBe(422);
      await expect(page.locator('#bo-video-add-errors')).toContainText(/YouTube/);
      await expect(page.locator('#bo-video-add-errors')).not.toContainText(/Vimeo/);
      expect(await videoIdsOf(page, productId)).toHaveLength(1);
      await page.goto(frontUrl);
      await expect(page.locator('.VideoPlayer')).toHaveCount(0);

      // Back on, the stored video plays again: nothing was lost.
      await setVimeo(true);
      await page.goto(frontUrl);
      await expect(page.locator('.VideoPlayer')).toHaveCount(1);
    } finally {
      await setVimeo(true);
      await deleteAllVideos(page, productId);
    }
  });

  test('the poster is the first image until another one is chosen, and the choice reaches the front', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);
    const youtube = PLATFORMS[0];

    try {
      expect(await addVideo(page, productId, { url: youtube.pasted }, 'Poster demo')).toBe(200);
      const [videoId] = await videoIdsOf(page, productId);

      await openFront(page, frontUrl);
      const defaultPoster = await page.locator('img.VideoPlayer-poster').getAttribute('src');
      expect(defaultPoster).toBeTruthy();

      // The thumbnail is chosen among the images of the product, on the video's own page.
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      const choice = page.getByTestId('bo-video-edit-thumbnail-choice');
      const options = await choice.locator('option').evaluateAll((nodes) => nodes.map((n) => (n as HTMLOptionElement).value).filter(Boolean));
      expect(options.length, 'the product must offer at least two images').toBeGreaterThanOrEqual(2);
      await choice.selectOption(options[1]);
      await page.getByTestId('bo-video-edit-save-stay').click();
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await expect(page.getByTestId('bo-video-edit-thumbnail-choice')).toHaveValue(options[1]);

      await openFront(page, frontUrl);
      const chosenPoster = await page.locator('img.VideoPlayer-poster').getAttribute('src');
      expect(chosenPoster).toBeTruthy();
      expect(chosenPoster).not.toBe(defaultPoster);

      // Back to "first product image": the default poster is shown again.
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await page.getByTestId('bo-video-edit-thumbnail-choice').selectOption('');
      await page.getByTestId('bo-video-edit-save-stay').click();
      await openFront(page, frontUrl);
      expect(await page.locator('img.VideoPlayer-poster').getAttribute('src')).toBe(defaultPoster);
    } finally {
      await deleteAllVideos(page, productId);
    }
  });

  test('a hidden video leaves the front, and comes back when shown again', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);

    try {
      expect(await addVideo(page, productId, { url: PLATFORMS[0].pasted }, 'Visibility demo')).toBe(200);
      const [videoId] = await videoIdsOf(page, productId);
      await openFront(page, frontUrl);
      await expect(page.locator('.VideoPlayer')).toHaveCount(1);

      await openImagesTab(page, productId);
      await Promise.all([
        page.waitForResponse((r) => r.url().includes(`/${videoId}/toggle`)),
        page.getByTestId(`bo-video-toggle-${videoId}`).click(),
      ]);
      await page.goto(frontUrl);
      await expect(page.locator('.VideoPlayer')).toHaveCount(0);

      await openImagesTab(page, productId);
      await Promise.all([
        page.waitForResponse((r) => r.url().includes(`/${videoId}/toggle`)),
        page.getByTestId(`bo-video-toggle-${videoId}`).click(),
      ]);
      await page.goto(frontUrl);
      await expect(page.locator('.VideoPlayer')).toHaveCount(1);
    } finally {
      await deleteAllVideos(page, productId);
    }
  });

  test('a video put first in the back-office grid opens the gallery on the front', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);

    try {
      expect(await addVideo(page, productId, { url: PLATFORMS[0].pasted }, 'Order demo')).toBe(200);
      const before = await mediaOrderOf(page, productId);
      expect(before[before.length - 1]).toMatch(/^video:/);

      // On the front the video is last, after every image.
      await page.goto(frontUrl);
      const thumbnails = page.locator('.ProductGallery-list li');
      await expect(thumbnails).toHaveCount(before.length);
      await expect(thumbnails.last().locator('.ProductGallery-videoBadge')).toHaveCount(1);
      await expect(thumbnails.first().locator('.ProductGallery-videoBadge')).toHaveCount(0);

      // Moved first in the back office, it is first on the front.
      const token = await gridToken(page, productId);
      const order = [before[before.length - 1], ...before.slice(0, -1)];
      expect((await postOrder(page, productId, order, token)).ok()).toBeTruthy();
      expect(await mediaOrderOf(page, productId)).toEqual(order);

      await page.goto(frontUrl);
      await expect(page.locator('.ProductGallery-list li').first().locator('.ProductGallery-videoBadge')).toHaveCount(1);
    } finally {
      await deleteAllVideos(page, productId);
    }
  });

  test('the source is replaced without losing what the merchant arranged around it', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);
    const sample = await recordSampleVideo(page);

    try {
      expect(await addVideo(page, productId, { url: PLATFORMS[0].pasted }, 'Source demo')).toBe(200);
      const [videoId] = await videoIdsOf(page, productId);

      // Give it a poster and an alternative text, the things a merchant would lose
      // by deleting the video and adding it again.
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      const choice = page.getByTestId('bo-video-edit-thumbnail-choice');
      const options = await choice.locator('option').evaluateAll((nodes) => nodes.map((n) => (n as HTMLOptionElement).value).filter(Boolean));
      await choice.selectOption(options[1]);
      await page.getByTestId('bo-video-edit-alt').fill('A demonstration of the bag');
      await page.getByTestId('bo-video-edit-save-stay').click();

      // The screen states the source it plays from.
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await expect(page.getByTestId('bo-video-edit-source')).toContainText(PLATFORMS[0].identifier);

      // An address of no known platform is refused, and nothing moves.
      await page.getByTestId('bo-video-edit-url').fill(UNKNOWN_URL);
      await page.getByTestId('bo-video-edit-save-stay').click();
      await expect(page.locator('.invalid-feedback, .form-error-message, [role="alert"]').first()).toContainText(/YouTube/);
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await expect(page.getByTestId('bo-video-edit-source')).toContainText(PLATFORMS[0].identifier);

      // Another platform address takes its place, and the rest is untouched.
      await page.getByTestId('bo-video-edit-url').fill(PLATFORMS[1].pasted);
      await page.getByTestId('bo-video-edit-save-stay').click();
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await expect(page.getByTestId('bo-video-edit-source')).toContainText(PLATFORMS[1].identifier);
      await expect(page.getByTestId('bo-video-edit-alt')).toHaveValue('A demonstration of the bag');
      await expect(page.getByTestId('bo-video-edit-thumbnail-choice')).toHaveValue(options[1]);
      expect(await videoIdsOf(page, productId)).toEqual([videoId]);

      // The front plays the new address.
      await openFront(page, frontUrl);
      await (await showVideoSlide(page)).locator('button.VideoPlayer-play').click();
      const frame = page.locator('iframe.VideoPlayer-frame');
      expect((await frame.getAttribute('src')) ?? '').toContain(PLATFORMS[1].identifier);

      // And a file takes the place of the address, served by the shop.
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await page.getByTestId('bo-video-edit-file').setInputFiles(sample);
      await page.getByTestId('bo-video-edit-save-stay').click();
      await page.goto(`/admin/video/product/${productId}/${videoId}/update`);
      await expect(page.getByTestId('bo-video-edit-source')).toContainText(/webm/i);

      await openFront(page, frontUrl);
      await (await showVideoSlide(page)).locator('button.VideoPlayer-play').click();
      const video = page.locator('video.VideoPlayer-file');
      await expect(video).toHaveCount(1);
      expect((await video.getAttribute('src')) ?? '').toMatch(/\/cache\/videos\//);
    } finally {
      await deleteAllVideos(page, productId);
      fs.rmSync(sample, { force: true });
    }
  });

  test('a video stops when the shopper moves to another visual', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);
    const sample = await recordSampleVideo(page);

    try {
      // A platform video: the frame is taken down and the poster comes back, which is
      // the only way to stop a player the shop does not script.
      expect(await addVideo(page, productId, { url: PLATFORMS[0].pasted }, 'Pause demo')).toBe(200);

      await openFront(page, frontUrl);
      const player = await showVideoSlide(page);
      await player.locator('button.VideoPlayer-play').click();
      await expect(page.locator('iframe.VideoPlayer-frame')).toHaveCount(1);

      await firstImageThumbnail(page).click();
      await expect(page.locator('iframe.VideoPlayer-frame')).toHaveCount(0);
      await expect(player.locator('img.VideoPlayer-poster')).toBeAttached();
      // One click starts it again.
      await (await showVideoSlide(page)).locator('button.VideoPlayer-play').click();
      await expect(page.locator('iframe.VideoPlayer-frame')).toHaveCount(1);

      // A file the shop serves is paused where it is, and keeps its place.
      await deleteAllVideos(page, productId);
      expect(await addVideo(page, productId, { file: sample }, 'Hosted pause demo')).toBe(200);

      await openFront(page, frontUrl);
      await (await showVideoSlide(page)).locator('button.VideoPlayer-play').click();
      const video = page.locator('video.VideoPlayer-file');
      await expect(video).toHaveCount(1);
      await video.evaluate((element: HTMLVideoElement) => element.play().catch(() => {}));
      await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(false);

      await firstImageThumbnail(page).click();
      await expect.poll(() => video.evaluate((element: HTMLVideoElement) => element.paused)).toBe(true);
      // Paused, not rewound: the element is still there with its position.
      await expect(video).toHaveCount(1);
    } finally {
      await deleteAllVideos(page, productId);
      fs.rmSync(sample, { force: true });
    }
  });

  test('an address whose video the platform cannot play is still accepted: only the platform knows', async ({ page }) => {
    const { productId, frontUrl } = await findProduct(page);
    await deleteAllVideos(page, productId);

    try {
      // Well-formed, eleven characters: nothing in the shop can tell it from a real one.
      expect(await addVideo(page, productId, { url: 'https://youtu.be/AAAAAAAAAAA' }, 'Unplayable')).toBe(200);
      await openFront(page, frontUrl);
      await (await showVideoSlide(page)).locator('button.VideoPlayer-play').click();
      const frame = page.locator('iframe.VideoPlayer-frame');
      await expect(frame).toHaveCount(1);
      expect(await frame.getAttribute('src')).toBe('https://www.youtube-nocookie.com/embed/AAAAAAAAAAA');
    } finally {
      await deleteAllVideos(page, productId);
    }
  });
});
