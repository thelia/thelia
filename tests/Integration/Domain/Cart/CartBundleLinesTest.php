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

namespace Thelia\Tests\Integration\Domain\Cart;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Form\CartAdd;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The places that judge a cart line on its stock outside the addition itself - the legacy form, the move
 * to another sale unit - judge a bundle line on what its components can supply, never on the stock of the
 * sale element that stands for the bundle.
 */
final class CartBundleLinesTest extends ActionIntegrationTestCase
{
    use BuildsBundles;

    private BundleCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
        $this->getService(PricingActivityChecker::class)->reset();
        ConfigQuery::write('check-available-stock', '1');
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        $this->getService(PricingActivityChecker::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheFormJudgesABundleOnItsComponents(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        // The sale element of the bundle claims a plenty: it is not what the form reads.
        $this->saleElementsOf($example['bundle'])->setQuantity(100)->save();

        // The default composition of the plan allows two bundles.
        self::assertTrue($this->submitAddToCart($example['bundle'], 2)->isValid());
        self::assertFalse($this->submitAddToCart($example['bundle'], 3)->isValid());
    }

    public function testTheFormRefusesABundleWhoseComponentIsOutOfStockWhateverItsOwnStock(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['bundle'])->setQuantity(100)->save();
        $this->saleElementsOf($example['bikeProduct'])->setQuantity(0)->save();

        self::assertFalse($this->submitAddToCart($example['bundle'], 1)->isValid());
    }

    public function testTheFormStillJudgesASimpleProductOnItsOwnStock(): void
    {
        $simple = $this->component($this->factory, '10', 3);

        self::assertTrue($this->submitAddToCart($simple, 3)->isValid());
        self::assertFalse($this->submitAddToCart($simple, 4)->isValid());
    }

    public function testMovingToAnotherSaleUnitKeepsABundleLineItsComponentsCanSupply(): void
    {
        [$cart, $example] = $this->cartHoldingABundle(2);
        $second = $this->factory->saleUnit();

        $this->moveTo($cart, $second);

        $line = $this->theLine($cart);
        self::assertNotNull($line, 'The stock of the sale element of the bundle, zero, is not what decides.');
        self::assertSame(2.0, (float) $line->getQuantity());
        self::assertNotNull($example);
    }

    public function testMovingToAnotherSaleUnitReducesABundleLineToWhatItsComponentsSupplyThere(): void
    {
        [$cart, $example] = $this->cartHoldingABundle(2);
        $second = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($second, $this->saleElementsOf($example['bikeProduct']), 1.0);

        $this->moveTo($cart, $second);

        self::assertSame(1.0, (float) $this->theLine($cart)?->getQuantity());
    }

    public function testMovingToAnotherSaleUnitRemovesABundleLineItsComponentsCannotSupplyThere(): void
    {
        [$cart, $example] = $this->cartHoldingABundle(2);
        $second = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($second, $this->saleElementsOf($example['bikeProduct']), 0.0);

        $this->moveTo($cart, $second);

        self::assertNull($this->theLine($cart));
    }

    /**
     * @return array{0: Cart, 1: array<string, mixed>}
     */
    private function cartHoldingABundle(int $quantity): array
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $first = $this->factory->saleUnit();
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);
        $cart->setSaleUnitId($first->getId())->save($this->getPropelConnection());

        $event = (new CartEvent($cart))
            ->setProductId((int) $example['bundle']->getId())
            ->setProductSaleElementsId((int) $this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true)
            ->setBundleComposition(BundleComposition::fromArray([
                ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
                ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
                ['bundleItemId' => $example['lock']->getId(), 'quantity' => 0],
            ]));
        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return [$cart, $example];
    }

    private function moveTo(Cart $cart, \Thelia\Model\SaleUnit $saleUnit): void
    {
        $this->getService(SaleUnitContext::class)->setSaleUnit($saleUnit);
        $this->dispatch(new CartEvent($cart), TheliaEvents::CART_FOLLOW_SALE_UNIT);
    }

    private function theLine(Cart $cart): ?CartItem
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
    }

    private function submitAddToCart(Product $product, int $quantity): FormInterface
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(CartAdd::class, FormType::class, [], ['csrf_protection' => false])
            ->getForm();

        $form->submit([
            'product' => (string) $product->getId(),
            'product_sale_elements_id' => (string) $this->saleElementsOf($product)->getId(),
            'quantity' => (string) $quantity,
            'append' => '1',
            'newness' => '0',
        ]);

        return $form;
    }
}
