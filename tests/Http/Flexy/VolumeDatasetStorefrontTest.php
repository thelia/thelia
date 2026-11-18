<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Http\Flexy;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\Volume\Command\GenerateVolumeDatasetCommand;
use Thelia\Test\Volume\VolumeDatasetGenerator;
use Thelia\Test\Volume\VolumeDatasetOptions;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The volume dataset is written by SQL, around the models: a generated product must still be a
 * product the shop sells, found in its category, shown on its page and put into the cart.
 */
final class VolumeDatasetStorefrontTest extends WebIntegrationTestCase
{
    private string $mediaDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaDirectory = sys_get_temp_dir().'/volume-dataset-'.bin2hex(random_bytes(4)).'/';
        (new Filesystem())->mkdir($this->mediaDirectory.'images/product');

        (new VolumeDatasetGenerator($this->getPropelConnection(), $this->mediaDirectory, GenerateVolumeDatasetCommand::IMAGE_SOURCE_DIRECTORY))
            ->generate(new VolumeDatasetOptions(
                products: 6,
                orders: 0,
                customers: 0,
                categoryDepth: 1,
                categoriesPerLevel: 2,
                locales: ['en_US', 'fr_FR'],
                currencyCodes: ['EUR', 'USD'],
                seed: 306,
                images: 2,
            ));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->mediaDirectory);

        parent::tearDown();
    }

    public function testAGeneratedCategoryListsItsProducts(): void
    {
        $product = $this->generatedProduct();
        $categoryId = $product->getDefaultCategoryId();

        $this->assertPageRenders('/'.$this->urlOf('category', $categoryId));

        self::assertStringContainsString((string) $product->setLocale('en_US')->getTitle(), (string) $this->client->getResponse()->getContent());
    }

    public function testAGeneratedProductIsShownAndGoesIntoTheCart(): void
    {
        $product = $this->generatedProduct();
        $cart = $this->openASessionWithACart();

        $this->assertPageRenders('/'.$this->urlOf('product', (int) $product->getId()));
        $sheet = $this->client->getCrawler();
        self::assertStringContainsString((string) $product->setLocale('en_US')->getTitle(), $sheet->filter('h1')->text());

        $this->addToCart($sheet, '1');

        self::assertSame(
            1,
            CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($product->getId())->count(),
            'The generated product must reach the cart.',
        );
    }

    private function generatedProduct(): \Thelia\Model\Product
    {
        return ProductQuery::create()->filterByRef('VOL-P0000001')->findOne($this->getPropelConnection())
            ?? self::fail('The dataset wrote no product.');
    }

    private function urlOf(string $view, int $id): string
    {
        $statement = $this->getPropelConnection()->prepare('SELECT url FROM rewriting_url WHERE view = ? AND view_id = ? AND view_locale = ?');
        $statement->execute([$view, (string) $id, 'en_US']);
        $row = $statement->fetch(\PDO::FETCH_NUM);

        return \is_array($row) ? (string) $row[0] : self::fail(\sprintf('No URL for the %s %d.', $view, $id));
    }

    /**
     * Submits the selector the way its form does in the browser: the quantity travels as the
     * updated value of the form the live component owns, and `save` runs on it.
     */
    private function addToCart(Crawler $sheet, string $quantity): void
    {
        $component = $sheet->filter('form.PseSelector-form')->ancestors()->filter('[data-live-url-value]')->first();
        self::assertCount(1, $component, 'The product page has no add-to-cart selector.');

        /** @var array<string, mixed> $props */
        $props = json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR);
        $formName = null;
        foreach ($props as $name => $value) {
            if (\is_array($value) && \array_key_exists('quantity', $value) && \array_key_exists('product_sale_elements_id', $value)) {
                $formName = $name;
            }
        }
        self::assertNotNull($formName, 'The selector carries no form values.');

        $this->client->request(
            'POST',
            \sprintf('%s/save', $component->attr('data-live-url-value')),
            ['data' => json_encode(['props' => $props, 'updated' => [$formName.'.quantity' => $quantity], 'args' => new \stdClass()], \JSON_THROW_ON_ERROR)],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.live-component+html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
    }

    private function openASessionWithACart(): Cart
    {
        $highestCartIdBefore = (int) CartQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();

        $this->client->request('GET', '/checkout/cart');

        return CartQuery::create()->filterById($highestCartIdBefore, Criteria::GREATER_THAN)->orderById(Criteria::DESC)->findOne()
            ?? self::fail('The cart page did not open a cart for this session.');
    }
}
