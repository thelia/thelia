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
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartItemQuantityAdjustedEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Exception\CartQuantityRefusedException;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Catalog\Quantity\QuantityAdjustment;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Checkout\Exception\CartQuantitiesAdjustedException;
use Thelia\Domain\Checkout\Service\Step\CartStepProvider;
use Thelia\Form\CartAdd;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The rules of a combination hold on every cart write. Where the buyer asks for a
 * quantity (the cart, its form, a module through CartFacade), a quantity off the
 * rules is refused with the valid ones; where a cart is read back (restored from its
 * cookie, checked before the checkout), a line off the rules is brought down or
 * removed and CART_ITEM_QUANTITY_ADJUSTED says so. A line a promotion offers is not
 * sold, and is held to none of it.
 */
final class CartQuantityRulesTest extends ActionIntegrationTestCase
{
    private Product $product;

    private ProductSaleElements $productSaleElements;

    private Cart $cart;

    /** @var list<CartItemQuantityAdjustedEvent> */
    private array $adjustments = [];

    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = $this->factory->currency();
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['baseQuantity' => 100]);
        $this->productSaleElements = $this->product->getDefaultSaleElements();
        $this->cart = $this->factory->cart(null, ['currency' => $currency]);
        $this->rule(new QuantityRule(minQuantity: 2.0, step: 6.0));

        $this->listener = function (CartItemQuantityAdjustedEvent $event): void {
            $this->adjustments[] = $event;
        };
        $this->dispatcher->addListener(TheliaEvents::CART_ITEM_QUANTITY_ADJUSTED, $this->listener);
    }

    protected function tearDown(): void
    {
        $this->dispatcher->removeListener(TheliaEvents::CART_ITEM_QUANTITY_ADJUSTED, $this->listener);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAQuantityTheRulesAcceptIsAdded(): void
    {
        $this->add(12);

        self::assertSame([12.0], $this->quantities());
    }

    public function testAQuantityOffTheStepIsRefusedWithTheValidOnes(): void
    {
        try {
            $this->add(7);
            self::fail('Seven is not sold by six.');
        } catch (CartQuantityRefusedException $refusal) {
            self::assertInstanceOf(InvalidCartException::class, $refusal, 'A theme that tells a refusal of the cart tells this one.');
            self::assertSame('Sold by 6: 6, 12, 18…', $refusal->getMessage());
            self::assertSame(QuantityAdjustment::OffStep, $refusal->decision?->adjustment);
        }

        self::assertSame([], $this->quantities(), 'Nothing is added.');
    }

    public function testAQuantityBelowTheMinimumIsRefused(): void
    {
        $this->rule(new QuantityRule(minQuantity: 3.0));

        $this->expectExceptionObject(new CartQuantityRefusedException('The smallest quantity you can order is 3.'));

        $this->add(2);
    }

    public function testAQuantitySoldByMeasureIsAddedWithItsDecimals(): void
    {
        $this->rule(new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2));

        $this->add(1.25);

        self::assertSame([1.25], $this->quantities());
    }

    public function testAnAdditionIsJudgedOnWhatTheLineWillHold(): void
    {
        $this->add(6);
        $this->add(6);

        try {
            $this->add(3);
            self::fail('Fifteen is not sold by six.');
        } catch (CartQuantityRefusedException) {
        }

        self::assertSame([12.0], $this->quantities(), 'The line keeps what it held.');
    }

    public function testAChangeFromTheCartIsJudgedByTheRules(): void
    {
        $line = $this->line(6);

        try {
            $this->change($line, 7);
            self::fail('Seven is not sold by six.');
        } catch (CartQuantityRefusedException $refusal) {
            self::assertSame('Sold by 6: 6, 12, 18…', $refusal->getMessage());
        }

        self::assertSame([6.0], $this->quantities());

        $this->change($line, 18);

        self::assertSame([18.0], $this->quantities());
    }

    public function testTheCartFormRefusesAQuantityOffTheStepWithTheValidOnes(): void
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(CartAdd::class, FormType::class, [], ['csrf_protection' => false])
            ->getForm();

        $form->submit([
            'product' => (string) $this->product->getId(),
            'product_sale_elements_id' => (string) $this->productSaleElements->getId(),
            'quantity' => '7',
            'append' => '1',
            'newness' => '0',
        ]);

        self::assertFalse($form->isValid());
        self::assertSame(['Sold by 6: 6, 12, 18…'], array_map(
            static fn ($error): string => $error->getMessage(),
            iterator_to_array($form->get('quantity')->getErrors()),
        ));
    }

    /**
     * Rules written around their validation (a minimum below the millionth a quantity
     * is counted in) cannot decide any quantity: the buyer is refused the way any other
     * quantity is refused, never shown a server error.
     */
    public function testRulesThatCannotDecideAQuantityRefuseTheAddition(): void
    {
        $this->unreadableRule();

        try {
            $this->add(1);
            self::fail('Rules that decide nothing cannot sell one.');
        } catch (CartQuantityRefusedException $refusal) {
            self::assertSame('The minimum quantity of a combination must be greater than zero.', $refusal->getMessage());
        }

        self::assertSame([], $this->quantities(), 'Nothing is added.');
    }

    public function testTheCartFormRefusesAQuantityOfRulesThatCannotDecideOne(): void
    {
        $this->unreadableRule();

        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(CartAdd::class, FormType::class, [], ['csrf_protection' => false])
            ->getForm();

        $form->submit([
            'product' => (string) $this->product->getId(),
            'product_sale_elements_id' => (string) $this->productSaleElements->getId(),
            'quantity' => '1',
            'append' => '1',
            'newness' => '0',
        ]);

        self::assertFalse($form->isValid());
        self::assertSame(['The minimum quantity of a combination must be greater than zero.'], array_map(
            static fn ($error): string => $error->getMessage(),
            iterator_to_array($form->get('quantity')->getErrors()),
        ));
    }

    public function testARestoredLineOffTheStepIsBroughtDownAndTold(): void
    {
        $line = $this->line(7);

        $this->restore();

        self::assertSame([6.0], $this->quantities());
        self::assertCount(1, $this->adjustments);
        self::assertSame((int) $line->getId(), (int) $this->adjustments[0]->getCartItem()->getId());
        self::assertSame(QuantityAdjustment::OffStep, $this->adjustments[0]->getDecision()->adjustment);
        self::assertSame('The quantity was brought down to 6: sold by 6.', $this->adjustments[0]->getMessage());
    }

    public function testARestoredLineBelowTheSmallestQuantityIsRemovedAndTold(): void
    {
        $this->line(5);

        $this->restore();

        self::assertSame([], $this->quantities());
        self::assertCount(1, $this->adjustments);
        self::assertTrue($this->adjustments[0]->isRemoved());
        self::assertSame('This line was removed: the smallest quantity you can order is 6.', $this->adjustments[0]->getMessage());
    }

    public function testARestoredLineWithinTheRulesIsLeftAlone(): void
    {
        $this->line(12);

        $this->restore();

        self::assertSame([12.0], $this->quantities());
        self::assertSame([], $this->adjustments);
    }

    public function testAnOfferedLineIsHeldToNoRule(): void
    {
        $this->line(1, ['isOffered' => 1]);

        $this->restore();

        self::assertSame([1.0], $this->quantities());
        self::assertSame([], $this->adjustments);
    }

    public function testAGiftAPromotionAddsIsHeldToNoRule(): void
    {
        $event = (new CartEvent($this->cart))
            ->setProductId($this->product->getId())
            ->setProductSaleElementsId($this->productSaleElements->getId())
            ->setQuantity(1)
            ->setAppend(false)
            ->setNewness(true)
            ->setOfferedByAPromotion(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        self::assertSame([1.0], $this->quantities());
    }

    /**
     * The gift of a free product coupon is an ordinary line, priced at zero by the
     * discount: only the session of the buyer knows it is the gift. Read back against the
     * rules of its combination, one gift sold by six would be removed.
     */
    public function testTheGiftOfAFreeProductCouponIsLeftToItsPromotion(): void
    {
        $gift = $this->line(1);
        $this->getService(RequestStack::class)->getMainRequest()?->getSession()->set(
            'coupon.free_product.cart_items.FREE-GIFT',
            [$this->product->getId() => (int) $gift->getId()],
        );

        $this->getService(CartStepProvider::class)->check($this->cart);

        self::assertSame([1.0], $this->quantities());
        self::assertSame([], $this->adjustments);
    }

    public function testTheCheckoutSendsTheBuyerBackToACartItHadToAdjust(): void
    {
        $this->line(7);
        $step = $this->getService(CartStepProvider::class);

        try {
            $step->check($this->cart);
            self::fail('The buyer must see the cart again before paying for six.');
        } catch (CartQuantitiesAdjustedException $refusal) {
            self::assertSame('cart-quantities-adjusted', $refusal->violationCode());
            self::assertSame(6.0, $refusal->violationDetails()['lines'][0]['quantity']);
        }

        self::assertSame([6.0], $this->quantities());
        self::assertCount(1, $this->adjustments);

        $step->check($this->cart);
        self::assertCount(1, $this->adjustments, 'The next check finds the cart in order.');
    }

    private function rule(QuantityRule $rule): void
    {
        $rule->applyTo($this->productSaleElements);
        $this->productSaleElements->save($this->getPropelConnection());
    }

    /**
     * A minimum stored as zero, written around the validation of the combination.
     */
    private function unreadableRule(): void
    {
        $this->productSaleElements->setMinQuantity('0.000000')->save($this->getPropelConnection());
    }

    private function add(float $quantity): void
    {
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            cart: $this->cart,
            productId: $this->product->getId(),
            productSaleElementId: $this->productSaleElements->getId(),
            quantity: $quantity,
        ));
    }

    private function change(CartItem $line, float $quantity): void
    {
        $this->getService(CartFacade::class)->updateItemQuantity(new CartItemUpdateQuantityDTO($this->cart, (int) $line->getId(), $quantity));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function line(float $quantity, array $overrides = []): CartItem
    {
        return $this->factory->cartItem($this->cart, $this->product, $this->productSaleElements, ['quantity' => $quantity] + $overrides);
    }

    /**
     * The visitor comes back with the cookie of the cart: the session does not know it yet.
     */
    private function restore(): void
    {
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $this->cart->getToken(),
        );

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);
        self::assertSame($this->cart->getId(), $event->getCart()?->getId());
    }

    /**
     * @return list<float>
     */
    private function quantities(): array
    {
        CartItemTableMap::clearInstancePool();

        $quantities = [];

        foreach (CartItemQuery::create()->filterByCartId($this->cart->getId())->orderById()->find($this->getPropelConnection()) as $line) {
            $quantities[] = round((float) $line->getQuantity(), 6);
        }

        return $quantities;
    }
}
