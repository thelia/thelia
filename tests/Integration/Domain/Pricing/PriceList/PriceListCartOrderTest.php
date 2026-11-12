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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceList;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Cart\Service\CartPriceGuard;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\PriceList\PriceListResolverInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\PriceList;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * The price of a price list from the screen to the invoice: the same price for the loop of
 * the sale elements, the data access of the themes, the cart and the order, and the tiers
 * of a price list follow the quantity of a line.
 *
 * Catalog price 100 untaxed, 20 % VAT; the list prices 10 from one unit and 9 from ten.
 */
final class PriceListCartOrderTest extends ActionIntegrationTestCase
{
    use ForgetsPooledModels;
    use LogsInAsCustomer;
    use PriceListFixtures;

    private Currency $currency;

    private Country $country;

    private CustomerGroup $group;

    private Customer $member;

    private Product $product;

    private ProductSaleElements $pse;

    private PriceList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
        $this->country = Country::getDefaultCountry();
        $this->group = $this->factory->customerGroup('pro');
        $this->member = $this->memberOf($this->group);
        $this->product = $this->factory->product($this->factory->category(), $this->taxRuleTaxingAt('20'), $this->currency, ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Tiered product']);
        $this->pse = $this->product->getDefaultSaleElements();
        $this->list = $this->priceList('PRO', $this->currency);
        $this->assignToGroup($this->list, $this->group);
        $this->forgetRequestMemos();
    }

    protected function tearDown(): void
    {
        $this->forgetRequestMemos();

        parent::tearDown();
    }

    /**
     * Nine units cost 10 each, ten units 9 each: the tier follows the quantity of the line,
     * on an addition that appends to the line as on a change of its quantity.
     */
    public function testTheTierOfALineFollowsItsQuantity(): void
    {
        $this->priceListItem($this->list, $this->pse, '10', 1);
        $this->priceListItem($this->list, $this->pse, '9', 10);
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);

        $line = $this->addToCart($cart, 9);
        self::assertSame('10.000000', $line->getPrice());
        self::assertSame('10.000000', $line->getPromoPrice());

        $this->changeQuantity($cart, $line, 10);
        self::assertSame('9.000000', $this->reloaded($line)->getPrice());

        $this->changeQuantity($cart, $line, 9);
        self::assertSame('10.000000', $this->reloaded($line)->getPrice());

        $this->addToCart($cart, 1);
        self::assertSame(10.0, (float) $this->reloaded($line)->getQuantity());
        self::assertSame('9.000000', $this->reloaded($line)->getPrice(), 'the addition that appends to the line moves it to the next tier');
    }

    /**
     * An exclusive list that holds the sale element from 10 units (10 -> 9) over a list of lower rank
     * (1 -> 7, 5 -> 6): the tiers published are the ones the cart applies, the list below included.
     */
    public function testTheTiersPublishedAreTheOnesTheCartAppliesBelowTheFirstTierOfAnExclusiveList(): void
    {
        $exclusive = $this->priceList('EXCL', $this->currency, priority: 10, exclusive: true);
        $this->assignToGroup($exclusive, $this->group);
        $this->priceListItem($exclusive, $this->pse, '9', 10);
        $this->priceListItem($this->list, $this->pse, '7', 1);
        $this->priceListItem($this->list, $this->pse, '6', 5);
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);

        $line = $this->addToCart($cart, 1);
        self::assertSame('7.000000', $line->getPrice());
        $this->changeQuantity($cart, $line, 5);
        self::assertSame('6.000000', $this->reloaded($line)->getPrice());
        $this->changeQuantity($cart, $line, 10);
        self::assertSame('9.000000', $this->reloaded($line)->getPrice());

        $published = $this->getService(PriceListResolverInterface::class)->tiersFor([$this->pse->getId()], $this->currency, $this->member)[(int) $this->pse->getId()];

        self::assertSame(
            [['minQuantity' => 1, 'untaxedPrice' => '7.000000'], ['minQuantity' => 5, 'untaxedPrice' => '6.000000'], ['minQuantity' => 10, 'untaxedPrice' => '9.000000']],
            $published,
        );

        foreach ($published as $tier) {
            $this->changeQuantity($cart, $line, $tier['minQuantity']);
            self::assertSame($tier['untaxedPrice'], $this->reloaded($line)->getPrice(), 'the cart charges the price the tier tells at its minimum quantity');
        }
    }

    public function testALineAddedAtTheQuantityOfATierIsWrittenAtThatTier(): void
    {
        $this->priceListItem($this->list, $this->pse, '10', 1);
        $this->priceListItem($this->list, $this->pse, '9', 10);

        $line = $this->addToCart($this->factory->cart($this->member, ['currency' => $this->currency]), 10);

        self::assertSame('9.000000', $line->getPrice());
    }

    public function testAListThatEndedRepricesTheLineAtTheLevelBelowAndTheCartPricerSeesItMoved(): void
    {
        $this->priceListItem($this->list, $this->pse, '10', 1);
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $line = $this->addToCart($cart, 2);
        self::assertSame('10.000000', $line->getPrice());

        $this->list->setEndsAt(new \DateTimeImmutable('-1 minute'))->save();
        $this->forgetRequestMemos();
        $cart->clearCartItems();
        $pricer = $this->getService(CartLinePricer::class);

        self::assertSame([(int) $line->getId()], array_map(static fn (CartItem $item): int => (int) $item->getId(), $pricer->staleLinesOf($cart, $this->currency, $this->member)));
        self::assertCount(1, $pricer->reprice($cart, $this->currency, $this->member));
        self::assertSame('100.000000', $this->reloaded($line)->getPrice(), 'no list left: the catalog price');
        self::assertSame([], $pricer->staleLinesOf($cart, $this->currency, $this->member));
    }

    /**
     * Where only price lists price, the guard of the payment settles the lines and stops on
     * the one that moved.
     */
    public function testTheGuardOfThePaymentRefusesACartWhoseListEnded(): void
    {
        $this->priceListItem($this->list, $this->pse, '10', 1);
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $this->addToCart($cart, 2);
        $this->list->setEndsAt(new \DateTimeImmutable('-1 minute'))->save();
        $this->forgetRequestMemos();
        $cart->clearCartItems();

        $this->expectException(CartPricesChangedException::class);

        $this->getService(CartPriceGuard::class)->settleOrRefuse($cart, $this->currency, $this->member);
    }

    /**
     * A shop pricing by lists alone: the price list, the data access, the loop, the line and the order all say 80.
     */
    public function testTheSamePriceIsReadEverywhereAndTheTaxesOfTheOrderRestOnIt(): void
    {
        $this->priceListItem($this->list, $this->pse, '80', 1);
        $this->forgetRequestMemos();
        $this->loginAsCustomerInSession($this->member);

        $resolved = $this->getService(CustomerPriceContextInterface::class)->resolve([$this->pse->getId()], $this->currency, $this->member)[$this->pse->getId()];
        $offered = json_decode((string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($this->product->getId()), true, flags: \JSON_THROW_ON_ERROR);
        $loopRow = null;

        foreach ($this->getService(LoopExecutor::class)->execute('product_sale_elements', ['product' => $this->product->getId()]) as $row) {
            $loopRow = $row->getVarVal();

            break;
        }

        self::assertNotNull($loopRow);
        self::assertEqualsWithDelta(80.0, $resolved->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(80.0, $offered[0]['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(96.0, $offered[0]['price'], 0.000001);
        self::assertEqualsWithDelta(80.0, (float) $loopRow['PRICE'], 0.000001);
        self::assertEqualsWithDelta(96.0, (float) $loopRow['TAXED_PRICE'], 0.000001);

        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $line = $this->addToCart($cart, 3);
        self::assertSame('80.000000', $line->getPrice());

        $order = $this->placeOrder($cart);
        $orderLines = OrderProductQuery::create()->filterByOrderId($order->getId())->find();

        self::assertCount(1, $orderLines);
        $orderLine = $orderLines->getFirst();
        self::assertEqualsWithDelta(80.0, (float) $orderLine->getPrice(), 0.000001);
        $taxes = OrderProductTaxQuery::create()->filterByOrderProductId($orderLine->getId())->find();
        self::assertCount(1, $taxes);
        self::assertEqualsWithDelta(16.0, (float) $taxes->getFirst()->getAmount(), 0.000001, '20 % of 80, not of the catalog price');
    }

    private function addToCart(Cart $cart, int $quantity): CartItem
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($this->product->getId())
            ->setProductSaleElementsId($this->pse->getId())
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true);
        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return $event->getCartItem() ?? self::fail('The line was not added.');
    }

    private function changeQuantity(Cart $cart, CartItem $line, int $quantity): void
    {
        $event = new CartEvent($cart);
        $event->setCartItemId($line->getId())->setQuantity($quantity);
        $this->dispatch($event, TheliaEvents::CART_UPDATEITEM);
    }

    private function reloaded(CartItem $line): CartItem
    {
        return CartItemQuery::create()->findPk($line->getId()) ?? self::fail('The line is gone.');
    }

    private function taxRuleTaxingAt(string $percent): TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->country->getId())
            ->setTaxId($this->factory->tax(['requirements' => ['percent' => $percent]])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }

    private function placeOrder(Cart $cart): Order
    {
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? self::fail('No delivery module installed.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque') ?? self::fail('No payment module installed.');

        $sessionOrder = new Order();
        $sessionOrder
            ->setDeliveryOrderAddressId($this->factory->orderAddress($this->country)->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress($this->country)->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        return $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $this->currency,
            Lang::getDefaultLanguage(),
            $cart,
            $this->member,
            useOrderDefinedAddresses: true,
        );
    }
}
