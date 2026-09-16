import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

const YOUTUBE_URL = 'https://youtu.be/dQw4w9WgXcQ';
const UNKNOWN_URL = 'https://example.com/video/1';

type Shop = {
  productId: string;
  imageId: string;
};

/**
 * The dev database is not fixed: no id is written down here. The first product
 * whose image tab has a grid is the one every test works on.
 */
async function findProductWithImages(page: Page): Promise<Shop> {
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
    const imageId = /data-testid="bo-file-alt-input-(\d+)"/.exec(html)?.[1];
    if (imageId) {
      return { productId, imageId };
    }
  }

  throw new Error('no product with an image found in this shop');
}

/**
 * Two distinct products that both have an image, for the cases that need a medium
 * belonging to someone else.
 */
async function findTwoProductsWithImages(page: Page): Promise<[Shop, Shop]> {
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

  const found: Shop[] = [];
  for (const productId of ids) {
    const response = await page.request.get(`/admin/image/type/product/${productId}/list-ajax`);
    if (!response.ok()) {
      continue;
    }
    const imageId = /data-testid="bo-file-alt-input-(\d+)"/.exec(await response.text())?.[1];
    if (imageId) {
      found.push({ productId, imageId });
    }
    if (found.length === 2) {
      return [found[0], found[1]];
    }
  }

  throw new Error('two products with an image are needed for this test');
}

async function openImagesTab(page: Page, productId: string, editLanguageId?: string): Promise<void> {
  const language = editLanguageId ? `&edit_language_id=${editLanguageId}` : '';
  await page.goto(`/admin/products/update?product_id=${productId}&current_tab=images${language}`);
  await expect(page.getByTestId('bo-file-list-grid')).toBeVisible();
}

async function openVideosTab(page: Page, productId: string): Promise<void> {
  await page.goto(`/admin/products/update?product_id=${productId}&current_tab=videos`);
  await expect(page.getByTestId('bo-video-panel')).toBeVisible();
}

async function addVideo(page: Page, productId: string, url: string, title: string): Promise<void> {
  await openVideosTab(page, productId);
  await page.getByTestId('bo-video-url').fill(url);
  await page.getByTestId('bo-video-title').fill(title);
  await page.getByTestId('bo-video-add-submit').click();
}

async function videoIdsOf(page: Page, productId: string): Promise<string[]> {
  const response = await page.request.get(`/admin/video/product/${productId}/list-ajax`);
  const html = await response.text();

  return Array.from(html.matchAll(/data-testid="bo-video-item-(\d+)"/g)).map((match) => match[1]);
}

async function deleteAllVideos(page: Page, productId: string): Promise<void> {
  for (const id of await videoIdsOf(page, productId)) {
    await openVideosTab(page, productId);
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByTestId(`bo-video-delete-${id}`).click();
    await expect(page.getByTestId(`bo-video-item-${id}`)).toHaveCount(0);
  }
}

test.describe('Back-office — product media: alternative text and videos (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('an inline alternative text is stored and read back', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);
    const alt = `Leather bag, front view ${Date.now()}`;

    await openImagesTab(page, productId);
    await page.getByTestId(`bo-file-alt-input-${imageId}`).fill(alt);
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();

    await openImagesTab(page, productId);
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toHaveValue(alt);
    await expect(page.getByTestId(`bo-file-alt-warning-${imageId}`)).toHaveClass(/d-none/);
  });

  test('an image with neither an alternative text nor the decorative flag is flagged', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);

    await openImagesTab(page, productId);
    await page.getByTestId(`bo-file-alt-input-${imageId}`).fill('');
    const decorative = page.getByTestId(`bo-file-decorative-${imageId}`);
    if (await decorative.isChecked()) {
      await decorative.uncheck();
    }
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();

    await openImagesTab(page, productId);
    await expect(page.getByTestId(`bo-file-alt-warning-${imageId}`)).toBeVisible();
    await expect(page.getByTestId(`bo-file-alt-warning-${imageId}`)).toContainText(/alternati/i);
  });

  test('the decorative box freezes the alternative text, clears the warning, and gives the wording back', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);
    const alt = `Leather bag, front view ${Date.now()}`;

    await openImagesTab(page, productId);
    await page.getByTestId(`bo-file-alt-input-${imageId}`).fill(alt);
    await page.getByTestId(`bo-file-decorative-${imageId}`).check();

    // Read-only, not disabled: still on the keyboard path, and it says why.
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toHaveAttribute('readonly', '');
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toBeEditable({ editable: false });
    await expect(page.getByTestId(`bo-file-decorative-hint-${imageId}`)).toBeVisible();
    await expect(page.getByTestId(`bo-file-alt-warning-${imageId}`)).toHaveClass(/d-none/);

    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();

    await openImagesTab(page, productId);
    await expect(page.getByTestId(`bo-file-decorative-${imageId}`)).toBeChecked();
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toHaveAttribute('readonly', '');

    // Unticking gives the merchant back the wording he had written.
    await page.getByTestId(`bo-file-decorative-${imageId}`).uncheck();
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();

    await openImagesTab(page, productId);
    await expect(page.getByTestId(`bo-file-decorative-${imageId}`)).not.toBeChecked();
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toHaveValue(alt);
  });

  test('a title-only post leaves the decorative flag alone', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);

    await openImagesTab(page, productId);
    await page.getByTestId(`bo-file-decorative-${imageId}`).check();
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();
    await openImagesTab(page, productId);
    await expect(page.getByTestId(`bo-file-decorative-${imageId}`)).toBeChecked();

    // What an old link or a module posts: a title, and nothing else.
    const token = await page.getByTestId(`bo-file-title-form-${imageId}`)
      .locator('input[name="_token"]')
      .inputValue();
    const posted = await page.request.post(`/admin/image/type/product/${imageId}/update-title`, {
      form: { title: 'Untouched', _token: token },
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    expect(posted.ok()).toBeTruthy();

    await openImagesTab(page, productId);
    await expect(page.getByTestId(`bo-file-decorative-${imageId}`)).toBeChecked();

    await page.getByTestId(`bo-file-decorative-${imageId}`).uncheck();
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();
  });

  test('a medium of another product cannot be attached to a combination', async ({ page }) => {
    const [mine, other] = await findTwoProductsWithImages(page);

    await page.goto(`/admin/products/update?product_id=${mine.productId}&current_tab=pse`);
    const pseId = await page.locator('[data-testid^="combinations-assoc-image-"]').first().getAttribute('data-pse-id');
    expect(pseId, 'the product must have at least one combination').toBeTruthy();
    const token = await page.locator('#pse-assoc-modal').getAttribute('data-bo-pse-assoc-picker-token-value');

    const mineOk = await page.request.post(`/admin/product_sale_elements/${pseId}/image/${mine.imageId}`, {
      form: { _token: token ?? '' },
    });
    expect(mineOk.status()).toBe(200);
    // Put it back: the call above is a toggle.
    await page.request.post(`/admin/product_sale_elements/${pseId}/image/${mine.imageId}`, { form: { _token: token ?? '' } });

    const foreign = await page.request.post(`/admin/product_sale_elements/${pseId}/image/${other.imageId}`, {
      form: { _token: token ?? '' },
    });
    expect(foreign.status()).toBe(404);
  });

  test('attaching a medium to a combination is refused without a token', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);

    await page.goto(`/admin/products/update?product_id=${productId}&current_tab=pse`);
    const pseId = await page.locator('[data-testid^="combinations-assoc-image-"]').first().getAttribute('data-pse-id');
    expect(pseId).toBeTruthy();

    // The Twig route used to be a GET that wrote: a page of someone else's could
    // make the admin follow it. It is a POST carrying a token now.
    const routes = await page.request.get('/admin/product_sale_elements/ajax/image/' + pseId);
    expect(routes.ok(), 'the read endpoint stays a GET').toBeTruthy();

    const withoutToken = await page.request.post(`/admin/product_sale_elements/${pseId}/image/${imageId}`, { form: {} });
    expect(withoutToken.status()).toBeGreaterThanOrEqual(400);
  });

  test('the media controls carry a name of their own', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);

    await openImagesTab(page, productId);
    // The thumbnail alt is legitimately empty (decorative, or not described yet):
    // the link has to be named without it.
    await expect(page.getByTestId('bo-file-thumb').first()).toHaveAttribute('aria-label', /\w/);

    await openVideosTab(page, productId);
    await expect(page.getByTestId('bo-video-add-submit')).toHaveAccessibleName(/\w/);
  });

  test('a refused address is announced on the field the merchant has to fix', async ({ page }) => {
    const { productId } = await findProductWithImages(page);
    await deleteAllVideos(page, productId);

    await addVideo(page, productId, UNKNOWN_URL, 'Refused');

    const url = page.getByTestId('bo-video-url');
    await expect(page.locator('#bo-video-add-errors')).toContainText('YouTube');
    await expect(page.locator('#bo-video-add-errors')).toHaveAttribute('role', 'alert');
    await expect(url).toHaveAttribute('aria-invalid', 'true');
    await expect(url).toHaveAttribute('aria-describedby', 'bo-video-add-errors');
    await expect(url).toBeFocused();
  });

  test('the missing alternative text warning follows a video too', async ({ page }) => {
    const { productId } = await findProductWithImages(page);
    await deleteAllVideos(page, productId);

    try {
      await addVideo(page, productId, YOUTUBE_URL, 'Demo');
      const [videoId] = await videoIdsOf(page, productId);

      await openVideosTab(page, productId);
      await expect(page.getByTestId(`bo-video-alt-warning-${videoId}`)).toBeVisible();

      await page.getByTestId(`bo-video-alt-input-${videoId}`).fill('Demonstration video of the bag');
      await expect(page.getByTestId(`bo-video-alt-warning-${videoId}`)).toHaveClass(/d-none/);

      await page.getByTestId(`bo-video-alt-input-${videoId}`).fill('');
      await expect(page.getByTestId(`bo-video-alt-warning-${videoId}`)).toBeVisible();
    } finally {
      await deleteAllVideos(page, productId);
    }
  });

  test('an alternative text is written per edition language', async ({ page }) => {
    const { productId, imageId } = await findProductWithImages(page);

    await openImagesTab(page, productId);
    const languageIds = await page.$$eval('[data-testid^="bo-language-switcher-"]', (links) =>
      Object.fromEntries(
        links.map((link) => [
          link.getAttribute('data-testid')?.replace('bo-language-switcher-', '') ?? '',
          /edit_language_id=(\d+)/.exec(link.getAttribute('href') ?? '')?.[1] ?? '',
        ]),
      ),
    );

    test.skip(!languageIds.fr || !languageIds.en, 'this shop has no French and English pair to switch between');

    const frenchAlt = `Sac en cuir vu de face ${Date.now()}`;
    const englishAlt = `Leather bag seen from the front ${Date.now()}`;

    await openImagesTab(page, productId, languageIds.fr);
    await page.getByTestId(`bo-file-alt-input-${imageId}`).fill(frenchAlt);
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();

    await openImagesTab(page, productId, languageIds.en);
    await page.getByTestId(`bo-file-alt-input-${imageId}`).fill(englishAlt);
    await page.getByTestId(`bo-file-inline-save-${imageId}`).click();

    await openImagesTab(page, productId, languageIds.fr);
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toHaveValue(frenchAlt);

    await openImagesTab(page, productId, languageIds.en);
    await expect(page.getByTestId(`bo-file-alt-input-${imageId}`)).toHaveValue(englishAlt);
  });

  test('a platform video is added, given a thumbnail, reordered, attached to a combination, then removed', async ({ page }) => {
    const { productId } = await findProductWithImages(page);
    await deleteAllVideos(page, productId);

    try {
      // An unknown address is refused, and the message names the platforms the shop accepts.
      await addVideo(page, productId, UNKNOWN_URL, 'Refused');
      await expect(page.getByTestId('bo-video-add-form')).toContainText('YouTube');
      await expect(page.getByTestId('bo-video-list-empty')).toBeVisible();

      // A YouTube address is accepted and shows up in the grid.
      await addVideo(page, productId, YOUTUBE_URL, 'Demo');
      await expect(page.getByTestId('bo-video-list-grid')).toBeVisible();

      const [firstVideoId] = await videoIdsOf(page, productId);
      expect(firstVideoId, 'the YouTube address must have produced a video').toBeTruthy();
      await expect(page.getByTestId(`bo-video-provider-${firstVideoId}`)).toContainText('YouTube');

      // The thumbnail is chosen among the product images.
      await page.goto(`/admin/video/product/${productId}/${firstVideoId}/update`);
      await expect(page.getByTestId('video-edit-page')).toBeVisible();
      const thumbnail = page.getByTestId('bo-video-edit-thumbnail-choice');
      const imageValue = await thumbnail
        .locator('option')
        .nth(1)
        .getAttribute('value');
      expect(imageValue, 'the product must offer an image as a thumbnail').toBeTruthy();
      await thumbnail.selectOption(imageValue!);
      await page.getByTestId('bo-video-edit-alt').fill('Demonstration video of the bag');
      await page.getByTestId('bo-video-edit-save-stay').click();

      await page.goto(`/admin/video/product/${productId}/${firstVideoId}/update`);
      await expect(page.getByTestId('bo-video-edit-thumbnail-choice')).toHaveValue(imageValue!);
      await expect(page.getByTestId('bo-video-edit-alt')).toHaveValue('Demonstration video of the bag');

      // A second video, then a reorder: the grid reads the stored positions back.
      await addVideo(page, productId, 'https://vimeo.com/76979871', 'Assembly');
      const ids = await videoIdsOf(page, productId);
      expect(ids.length).toBe(2);

      await openVideosTab(page, productId);
      const token = await page.getByTestId('bo-video-list-grid')
        .locator('xpath=ancestor::div[@data-controller="bo-file-list"]')
        .getAttribute('data-bo-file-list-token-value');
      const moved = await page.request.post(`/admin/video/product/${productId}/update-position`, {
        form: { file_id: ids[1], position: '1', _token: token ?? '' },
      });
      expect(moved.ok()).toBeTruthy();
      expect(await videoIdsOf(page, productId)).toEqual([ids[1], ids[0]]);

      // The combination picker offers the videos of the product.
      await page.goto(`/admin/products/update?product_id=${productId}&current_tab=pse`);
      const assocButton = page.locator('[data-testid^="combinations-assoc-video-"]').first();
      await expect(assocButton).toBeVisible();
      await assocButton.click();
      const card = page.getByTestId(`pse-assoc-item-${ids[0]}`);
      await expect(card).toBeVisible();
      await card.click();
      await expect(card).toHaveAttribute('data-associated', '1');
    } finally {
      await deleteAllVideos(page, productId);
      expect(await videoIdsOf(page, productId)).toEqual([]);
    }
  });
});
