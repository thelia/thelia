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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Action\Coupon as CouponAction;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartProductVisibilitySubscriber;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * What a cart holds, at what its visitor pays, follows the visitor on the two roads that put a
 * cart in the hands of somebody else than the one who filled it: the sign-in of a form (the cart
 * of the session is attached, not restored), and the cookie that finds a cart again.
 *
 * The example: the professionals pay 15 % less than the public. A visitor puts a product at 100
 * in the cart, a promotion takes 10 % off the cart, and the visitor signs in as a professional:
 * the line is 85, the discount 8.5 and not 10, the carrier quotes the lines that are left.
 */
final class CartSettledForTheVisitorTest extends ActionIntegrationPromotionTestCase
{
    /** @var list<int> the number of lines the carrier quoted, one entry per quotation */
    private array $quotations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotations = [];
        // The carrier asks 5 per line of the cart: the postage tells how many lines it quoted.
        $this->dispatcher->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            function (DeliveryPostageEvent $event): void {
                $lines = CartItemQuery::create()->filterByCartId($event->getCart()->getId())->filterByIsOffered(0)->count();
                $this->quotations[] = $lines;
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(5.0 * $lines, 0.0, 'VAT'));
                $event->stopPropagation();
            },
            512,
        );
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->remove(ConfigQuery::read('cart.cookie_name', 'thelia_cart'));
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testASessionCartFilledAsAVisitorIsPricedForTheGroupOfTheCustomerWhoSignsIn(): void
    {
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $professional = $this->customerOf($this->factory->customerGroup('pro-signin', 15.0));
        $cart = $this->guestCartWithOneLine(100.0, $professional);
        $line = $this->singleLine($cart);
        self::assertSame(100.0, round((float) $line->getPrice(), 6), 'the visitor filled the cart at the public price');
        self::assertEqualsWithDelta(10.0, $this->discountOf($cart), 0.02);
        $this->quotations = [];

        $this->dispatch(new CustomerLoginEvent($professional), TheliaEvents::CUSTOMER_LOGIN);

        self::assertSame(85.0, round((float) $this->singleLine($cart)->getPrice(), 6), 'the line is at the price of the group');
        self::assertEqualsWithDelta(8.5, $this->discountOf($cart), 0.02, '10 % of the price they will pay, not of the one the visitor saw');
        self::assertSame((int) $professional->getId(), (int) CartQuery::create()->findPk($cart->getId())?->getCustomerId());
        self::assertSame([1], $this->quotations, 'the carrier quotes the lines once, after the discount');
    }

    public function testASignInThatMovesNothingWritesNothingAndAsksNoCarrier(): void
    {
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $this->factory->customerGroup('pro-unused-signin', 15.0);
        $consumer = $this->customerOf(null);
        $cart = $this->guestCartWithOneLine(100.0, $consumer);
        $updatedAt = $this->singleLine($cart)->getUpdatedAt('U.u');
        $this->quotations = [];

        $this->dispatch(new CustomerLoginEvent($consumer), TheliaEvents::CUSTOMER_LOGIN);

        self::assertSame(100.0, round((float) $this->singleLine($cart)->getPrice(), 6));
        self::assertSame($updatedAt, $this->singleLine($cart)->getUpdatedAt('U.u'), 'a line that did not move is not written');
        self::assertSame([], $this->quotations);
    }

    public function testTheSettlementRunsAboveThePromotionsAndTheCarrierBelowThem(): void
    {
        // The contract the sign-in relies on: the promotions (Action\Coupon) read the lines the
        // settlement writes, and the carrier reads the discount the promotions write.
        $priorities = [];

        foreach (CartProductVisibilitySubscriber::getSubscribedEvents()[TheliaEvents::CUSTOMER_LOGIN] as [$method, $priority]) {
            $priorities[$method] = $priority;
        }

        [, $couponPriority] = CouponAction::getSubscribedEvents()[TheliaEvents::CUSTOMER_LOGIN];

        self::assertGreaterThan($couponPriority, $priorities['settleTheCartOfTheCustomer']);
        self::assertLessThan($couponPriority, $priorities['quoteThePostageAgainAtSignIn']);
    }

    public function testACartFoundAgainByItsCookieHasItsDiscountItsGiftAndItsPostagePricedOnTheLinesThatAreLeft(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '1');
        $sofas = $this->factory->category();
        $diningRoom = $this->factory->category();
        $table = $this->productIn($diningRoom, ['basePrice' => 100.0]);
        $sofa = $this->productIn($sofas, ['basePrice' => 100.0]);
        $gift = $this->productIn($sofas, ['basePrice' => 7.0]);
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $this->automaticPromotion(
            type: 'thelia.coupon.type.buy_x_get_y',
            effects: [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [$table->getId()],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 1,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => $gift->getId(),
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ],
            conditions: $this->atLeastOneLine(),
            title: 'Buy a table, get a gift',
        );

        // A visitor with no account fills the cart while the shop shows them everything.
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $table);
        $this->addItem($cart, $sofa);
        $this->giveTheCartACarrier($cart);
        $discountBefore = $this->discountOf($cart);
        $cart->reload();
        self::assertCount(1, $this->offeredLines($cart), 'the table offers the gift');
        $token = (string) $cart->getToken();

        // The shop closes the dining room to the public; the visitor comes back with the cookie.
        $this->restrictThePublicTo($sofas);
        $this->getService('services_resetter')->reset();
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $token);
        $this->quotations = [];

        $restored = $this->session()->getSessionCart($this->dispatcher);

        self::assertSame((int) $cart->getId(), (int) $restored->getId(), 'the cookie found the same cart');
        self::assertSame([(int) $sofa->getId()], $this->productIdsOf($cart, 0), 'the table went out of the cart');
        self::assertSame([], $this->offeredLines($cart), 'the gift of the table went with it');
        $discountAfter = $this->discountOf($cart);
        self::assertLessThan($discountBefore, $discountAfter, 'the discount is not the one of the old lines');
        $this->recomputeDiscount();
        self::assertEqualsWithDelta($this->discountOf($cart), $discountAfter, 0.0001, 'the discount was priced on the lines that are left');
        self::assertSame([1], $this->quotations, 'the carrier quoted the one line that is left');
        self::assertSame(5.0, (float) CartQuery::create()->findPk($cart->getId())?->getPostage());
    }

    public function testACartFoundAgainByItsCookieThatLostNothingIsNotPricedAgain(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '1');
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));
        $this->giveTheCartACarrier($cart);
        $token = (string) $cart->getToken();
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $token);
        $this->quotations = [];

        $this->session()->getSessionCart($this->dispatcher);

        self::assertSame([], $this->quotations);
    }

    /**
     * @return list<int>
     */
    private function productIdsOf(Cart $cart, int $offered): array
    {
        CartItemTableMap::clearInstancePool();

        return array_map('intval', CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered($offered)->orderById()->select('ProductId')->find()->getData());
    }

    /**
     * @return list<CartItem>
     */
    private function offeredLines(Cart $cart): array
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(1)->find()->getData();
    }

    private function restrictThePublicTo(Category $category): void
    {
        $public = CustomerGroupQuery::create()->filterByIsDefault(1)->findOne();
        self::assertNotNull($public);
        $public->setRestrictsCategories(1)->save();
        (new CustomerGroupCategory())->setCustomerGroupId($public->getId())->setCategoryId($category->getId())->save();
    }

    private function customerOf(?CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), null === $group ? [] : ['customerGroup' => $group]);
    }

    /**
     * The cart of a visitor with no account, one product at this public price, a carrier chosen
     * on an address the visitor will turn out to own.
     */
    private function guestCartWithOneLine(float $price, Customer $futureOwner): Cart
    {
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => $price]));
        $this->giveTheCartACarrier($cart, $futureOwner);

        return $cart;
    }

    private function giveTheCartACarrier(Cart $cart, ?Customer $addressOf = null): void
    {
        $owner = $addressOf ?? $this->customerOf(null);
        $cartAddress = $this->factory->cartAddress($this->factory->address($owner));
        $cart
            ->setAddressDeliveryId($cartAddress->getId())
            ->setAddressInvoiceId($cartAddress->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPostage('5')
            ->setPostageTax('0')
            ->save();
        $cart->reload();
        $this->recomputeDiscount();
        $cart->reload();
    }

    private function singleLine(Cart $cart): CartItem
    {
        CartItemTableMap::clearInstancePool();

        $line = CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(0)->findOne();
        self::assertNotNull($line);

        return $line;
    }

    private function discountOf(Cart $cart): float
    {
        CartTableMap::clearInstancePool();
        CustomerTableMap::clearInstancePool();

        return (float) CartQuery::create()->findPk($cart->getId())?->getDiscount();
    }

    private function atLeastOneLine(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
