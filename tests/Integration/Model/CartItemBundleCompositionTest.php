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

namespace Thelia\Tests\Integration\Model;

use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * Whoever writes a cart line, a line is a bundle line when it holds a composition, and a bundle is only
 * sold with one.
 */
final class CartItemBundleCompositionTest extends IntegrationTestCase
{
    use BuildsBundles;

    public function testALineOfABundleWithoutCompositionIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $cart = $factory->cart();

        $this->expectException(InvalidBundleCompositionException::class);

        $factory->cartItem($cart, $example['bundle']);
    }

    public function testACompositionOnALineOfAProductThatIsNotABundleIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $cart = $factory->cart();

        $line = (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bikeProduct']->getId())
            ->setProductSaleElementsId($this->saleElementsOf($example['bikeProduct'])->getId())
            ->setQuantity(1)
            ->setPrice('10')
            ->setPromoPrice('10')
            ->setBundleComposition('[{"bundleItemId":1,"quantity":"1"}]');

        $this->expectException(InvalidBundleCompositionException::class);

        $line->save($this->getPropelConnection());
    }

    public function testAnUpdateThatOnlyChangesTheCompositionIsJudgedOnTheProduct(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $cart = $factory->cart();

        // A plain line given a composition, and a pack line stripped of its own: neither moves its product.
        $plain = $factory->cartItem($cart, $example['bikeProduct']);
        $plain->setBundleComposition('[{"bundleItemId":1,"quantity":"1"}]');

        try {
            $plain->save($this->getPropelConnection());
            self::fail('A product that is not a bundle takes no composition.');
        } catch (InvalidBundleCompositionException) {
            self::assertNull(CartItemQuery::create()->findPk($plain->getId())?->getBundleComposition());
        }

        $pack = (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bundle']->getId())
            ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity(1)
            ->setPrice('10')
            ->setPromoPrice('10')
            ->setBundleCompositionLines([['bundleItemId' => $example['bike']->getId(), 'quantity' => 1], ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2]]);
        $pack->save($this->getPropelConnection());
        $pack->setBundleComposition(null);

        try {
            $pack->save($this->getPropelConnection());
            self::fail('A bundle line keeps its composition.');
        } catch (InvalidBundleCompositionException) {
            self::assertNotNull(CartItemQuery::create()->findPk($pack->getId())?->getBundleComposition());
        }
    }

    public function testALineAPromotionOffersIsNotJudgedOnItsComposition(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $cart = $factory->cart();

        $line = $factory->cartItem($cart, $example['bundle'], null, ['isOffered' => 1]);

        self::assertNotNull($line->getId());
    }

    public function testTheStockOfTheSaleElementOfTheBundleIsNeverReadByTheLine(): void
    {
        ConfigQuery::write('check-available-stock', '1');

        try {
            $factory = $this->createFixtureFactory();
            $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
            self::assertSame(0.0, (float) $this->saleElementsOf($example['bundle'])->getQuantity());
            $line = (new CartItem())
                ->setCartId($factory->cart()->getId())
                ->setProductId($example['bundle']->getId())
                ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
                ->setQuantity(1)
                ->setPrice('10')
                ->setPromoPrice('10')
                ->setBundleCompositionLines([['bundleItemId' => $example['bike']->getId(), 'quantity' => 1], ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2]]);
            $line->save($this->getPropelConnection());

            // Without a ceiling from the caller the line asks nothing of the sale element, which holds nothing.
            $line->updateQuantity(2);
            self::assertSame(2.0, (float) $line->getQuantity());
            $line->addQuantity(1);
            self::assertSame(3.0, (float) $line->getQuantity());

            // With the ceiling the caller gives, the line is held to it.
            $this->expectException(NotEnoughStockException::class);
            $line->updateQuantity(5, 4.0);
        } finally {
            ConfigQuery::resetCache();
        }
    }

    public function testALineThatHoldsACompositionIsJudgedOnItsProductWhateverTheCatalogFlagSays(): void
    {
        $before = ConfigQuery::read(BundleCatalogState::CONFIG_KEY, '0');

        try {
            $factory = $this->createFixtureFactory();
            $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
            // The flag lags behind the catalog: it says no bundle while the product is one.
            // ConfigQuery::read() answers '0' as a value, the write must go through ConfigQuery::write().
            ConfigQuery::write(BundleCatalogState::CONFIG_KEY, '0');
            self::assertFalse((new BundleCatalogState())->hasBundles());

            $line = (new CartItem())
                ->setCartId($factory->cart()->getId())
                ->setProductId($example['bundle']->getId())
                ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
                ->setQuantity(1)
                ->setPrice('10')
                ->setPromoPrice('10')
                ->setBundleCompositionLines([['bundleItemId' => $example['bike']->getId(), 'quantity' => 1], ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2]]);
            $line->save($this->getPropelConnection());

            self::assertNotNull($line->getId());
        } finally {
            ConfigQuery::write(BundleCatalogState::CONFIG_KEY, \is_string($before) ? $before : '0');
            ConfigQuery::resetCache();
        }
    }

    public function testTheCompositionReadsAndWritesAsAList(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $lines = [
            ['bundleItemId' => (int) $example['lock']->getId(), 'quantity' => '2'],
            ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => 1],
        ];

        $line = (new CartItem())->setBundleCompositionLines($lines);

        self::assertSame(BundleComposition::fromArray($lines)->toCanonicalJson(), $line->getBundleComposition());
        self::assertSame(
            [
                ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => '1'],
                ['bundleItemId' => (int) $example['lock']->getId(), 'quantity' => '2'],
            ],
            $line->getBundleCompositionLines(),
        );
        self::assertNull($line->setBundleCompositionLines(null)->getBundleCompositionLines());
    }
}
