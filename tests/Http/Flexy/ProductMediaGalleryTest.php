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

use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A merchant arranges the visuals of a product sheet — images and videos alike — in one
 * order, gives each image the text a screen reader reads out, and marks the ones that say
 * nothing as decorative.
 *
 * These tests pin what a shopper gets out of that: a video sits where the merchant put it
 * among the images, nothing is requested from the platform before the shopper asks for it,
 * a decorative image is announced by nothing while an unnamed one falls back to its title
 * then to the product's, every player carries a name, and a video taken offline or left
 * stranded by a platform the shop no longer serves is nowhere on the page.
 *
 * The page belongs to the front-office theme, which ships as its own package on its own
 * release cycle: a theme older than the video player is reported as skipped rather than
 * failed.
 */
final class ProductMediaGalleryTest extends WebIntegrationTestCase
{
    private const PRODUCT_URL = 'flexy-product-media-test.html';

    private const PRODUCT_TITLE = 'Product media page under test';

    protected function setUp(): void
    {
        // The guard names a class shipped *with* the feature, not one the theme already had:
        // an older theme has the gallery, it just cannot play a video.
        if (!class_exists(\FlexyBundle\Components\Organisms\VideoPlayer\Base::class)) {
            self::markTestSkipped('The installed front-office theme has no video player.');
        }

        parent::setUp();
    }

    public function testAVideoIsShownWhereTheMerchantPutItAmongTheImages(): void
    {
        $product = $this->productUnderTest();
        $factory = $this->factory();

        $factory->productImage($product, ['position' => 1, 'title' => 'The first image']);
        $video = $factory->productVideo($product, ['position' => 2, 'title' => 'The middle video', 'alt' => 'A demonstration of the product']);
        $factory->productImage($product, ['position' => 3, 'title' => 'The last image']);

        $content = $this->renderProductPage();

        $slides = self::slidePositions($content);

        self::assertCount(3, $slides, 'The gallery carries one slide per visual.');
        self::assertStringContainsString(
            'data-Organisms--VideoPlayer--base-embed-url-value',
            $slides[1],
            'The video the merchant placed second is the second visual of the gallery.',
        );
        self::assertStringContainsString('dQw4w9WgXcQ', $slides[1]);
        self::assertNotNull($video->getId());
    }

    /**
     * The shop does not call the platform on behalf of a shopper who has not asked to watch
     * anything: no frame, no media element, only a button and the address it will use.
     */
    public function testNoFrameAndNoPlatformRequestBeforeTheShopperAsksForOne(): void
    {
        $product = $this->productUnderTest();
        $this->factory()->productVideo($product, ['position' => 1, 'alt' => 'A demonstration of the product']);

        $content = $this->renderProductPage();

        self::assertStringNotContainsString('<iframe', $content, 'No frame is rendered before the click.');
        self::assertStringNotContainsString('<video', $content, 'No media element is rendered before the click.');
        self::assertStringContainsString(
            'data-Organisms--VideoPlayer--base-embed-url-value="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"',
            $content,
            'The address the player will use is carried by the controller, not by a frame.',
        );
        self::assertStringContainsString('aria-label="A demonstration of the product"', $content);
    }

    public function testADecorativeImageIsAnnouncedByNothing(): void
    {
        $product = $this->productUnderTest();
        $image = $this->factory()->productImage($product, ['position' => 1, 'title' => 'A title nobody should hear']);
        $image->setDecorative(1)->save($this->getPropelConnection());
        $image->setLocale('en_US')->setAlt('An alternative nobody should hear either')->save($this->getPropelConnection());

        $content = $this->renderProductPage();

        self::assertStringContainsString('alt=""', $content, 'A decorative image carries an empty alt.');
        self::assertStringNotContainsString('A title nobody should hear', $content);
        self::assertStringNotContainsString('An alternative nobody should hear either', $content);
    }

    public function testAnImageWithNoAlternativeTextFallsBackToItsTitle(): void
    {
        $product = $this->productUnderTest();
        $this->factory()->productImage($product, ['position' => 1, 'title' => 'The bag seen from the front']);

        $content = $this->renderProductPage();

        self::assertStringContainsString('alt="The bag seen from the front"', $content);
    }

    public function testAnImageWithAnAlternativeTextCarriesIt(): void
    {
        $product = $this->productUnderTest();
        $image = $this->factory()->productImage($product, ['position' => 1, 'title' => 'A short title']);
        $image->setLocale('en_US')->setAlt('The bag, open, with its shoulder strap folded inside')->save($this->getPropelConnection());

        $content = $this->renderProductPage();

        self::assertStringContainsString('alt="The bag, open, with its shoulder strap folded inside"', $content);
        self::assertStringNotContainsString('alt="A short title"', $content);
    }

    /**
     * An empty alt tells a screen reader to skip the image, which is what the merchant asked
     * for on a decorative one and on nothing else. An image nobody got round to naming is
     * still a picture of the product, and is announced with the product's own name.
     */
    public function testAnImageNobodyNamedIsAnnouncedWithTheProductName(): void
    {
        $product = $this->productUnderTest();
        $this->factory()->productImage($product, ['position' => 1]);

        $content = $this->renderProductPage();

        self::assertStringContainsString('alt="'.self::PRODUCT_TITLE.'"', $content);
        self::assertStringNotContainsString(
            'alt=""',
            $content,
            'Only a decorative image is announced by nothing.',
        );
    }

    /**
     * A video nobody titled still has a player, and a player with no name is a frame a screen
     * reader announces as nothing at all.
     */
    public function testAVideoNobodyNamedStillHasANamedPlayer(): void
    {
        $product = $this->productUnderTest();
        $this->factory()->productVideo($product, ['position' => 1]);

        $content = $this->renderProductPage();

        self::assertStringContainsString(
            'data-Organisms--VideoPlayer--base-label-value="Play video"',
            $content,
            'The name the frame will carry is decided before the click, and is never empty.',
        );
        self::assertStringNotContainsString('-label-value=""', $content);
    }

    /**
     * A merchant who stops offering a platform leaves behind videos the shop can no longer
     * address. They are dropped from the gallery outright — a thumbnail that opens an empty
     * slide is worse than no thumbnail.
     */
    public function testAVideoOnAPlatformTheShopNoLongerServesIsNowhereOnThePage(): void
    {
        $product = $this->productUnderTest();
        $factory = $this->factory();

        $factory->productImage($product, ['position' => 1, 'title' => 'The only visual left']);
        $factory->productVideo($product, [
            'position' => 2,
            'provider' => 'retired-platform',
            'externalId' => 'strandedVideoId',
            'alt' => 'A video nothing can play any more',
        ]);

        $content = $this->renderProductPage();

        self::assertCount(1, self::slidePositions($content), 'The stranded video leaves no empty slide.');
        // Not the bare name: the importmap lists every controller of the theme, the video
        // player's among them, whether or not the page mounts one.
        self::assertStringNotContainsString(
            'data-controller="Organisms--VideoPlayer--base"',
            $content,
            'And no player either.',
        );
        self::assertStringNotContainsString('strandedVideoId', $content);
        self::assertStringNotContainsString('A video nothing can play any more', $content);
        self::assertStringContainsString('alt="The only visual left"', $content);
    }

    public function testAVideoTakenOfflineIsNowhereOnThePage(): void
    {
        $product = $this->productUnderTest();
        $factory = $this->factory();

        $factory->productImage($product, ['position' => 1, 'title' => 'The only visual left']);
        $factory->productVideo($product, [
            'position' => 2,
            'visible' => 0,
            'externalId' => 'hiddenVideoId',
            'alt' => 'A video the merchant took offline',
        ]);

        $content = $this->renderProductPage();

        self::assertStringNotContainsString('hiddenVideoId', $content);
        self::assertStringNotContainsString('A video the merchant took offline', $content);
        self::assertStringContainsString('alt="The only visual left"', $content);
    }

    private function renderProductPage(): string
    {
        $this->assertPageRenders('/'.self::PRODUCT_URL);

        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * The slides of the main gallery, in the order the page renders them.
     *
     * @return list<string>
     */
    private static function slidePositions(string $content): array
    {
        preg_match_all('#<li\s+class="splide__slide".*?</li>#s', $content, $matches);

        return $matches[0];
    }

    private function productUnderTest(): Product
    {
        $product = $this->product($this->factory()->category(), self::PRODUCT_TITLE);
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        return $product;
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the page render below — the
     * one the session, and therefore the current language, is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function product(Category $category, string $title): Product
    {
        $factory = $this->factory();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());

        $product->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $product;
    }
}
