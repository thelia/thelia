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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Order\RecordsStatements;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * What placing an order costs in statements, for a shop that does not reserve its stock.
 */
final class OrderPlacementQueryBudgetTest extends ActionIntegrationTestCase
{
    use PlacesBundleOrders;
    use RecordsStatements;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        // The transaction this test wraps itself in is not one of a caller: the stock is told at once.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        ConfigQuery::resetCache();

        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    protected function tearDown(): void
    {
        $this->getService(TestCallerTransaction::class)->reset();
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * A shop that does not reserve its stock and sells nothing below zero runs, for each line of
     * an order, the statements it ran before the sale at zero stock and the reservation: one
     * guarded UPDATE takes the stock, the sale mode read in it, and a line costs the ten
     * statements it cost on 3.3, whose take was that one UPDATE. Per order, two reads come in:
     * what the takes left, read once for the stock event, and the thresholds the low stock
     * listener reads on that event.
     */
    public function testAShopThatDoesNotReserveRunsForEachLineTheStatementsItHad(): void
    {
        [$customer, $currency, $lang] = $this->aBuyer();
        // A first placement warms the caches the measured ones then share.
        $this->placementOf($customer, $currency, $lang, 1)();

        $one = $this->statementsDuring($this->placementOf($customer, $currency, $lang, 1));
        $two = $this->statementsDuring($this->placementOf($customer, $currency, $lang, 2));

        self::assertSame(10, \count($two) - \count($one), 'A line costs what it cost before.');
        self::assertCount(2, array_filter($two, self::isATakeOfTheStock(...)), 'One statement takes the stock of each line.');
        self::assertCount(2, array_filter($two, self::isAReadOfTheStockEvent(...)), 'What the takes left and the thresholds, each read once for the order.');
        self::assertCount(2, array_filter($one, self::isAReadOfTheStockEvent(...)));
        self::assertSame([], array_values(array_filter($two, static fn (string $statement): bool => str_contains($statement, 'reserved_until') && !str_starts_with($statement, 'SELECT `cart_item`.`id`'))), 'Nothing of the reservation is read.');
    }

    private static function isATakeOfTheStock(string $statement): bool
    {
        return str_starts_with($statement, 'UPDATE `product_sale_elements`') && str_contains($statement, '`quantity` - :quantity');
    }

    private static function isAReadOfTheStockEvent(string $statement): bool
    {
        return str_starts_with($statement, 'SELECT `id`, `quantity`, `out_of_stock_policy`, `restock_date` FROM `product_sale_elements` WHERE `id` IN')
            || str_contains($statement, '`product_sale_elements`.`low_stock_threshold` AS "LowStockThreshold"');
    }

    /**
     * A cart of as many lines as asked, each of its own product, and the placement of its order,
     * ready to run: everything but the placement is written before.
     */
    private function placementOf(Customer $customer, Currency $currency, Lang $lang, int $lines): \Closure
    {
        $cart = $this->aCart($customer, $currency);

        for ($line = 0; $line < $lines; ++$line) {
            $this->addSimpleLine($cart, $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['baseQuantity' => 10]), 1.0);
        }

        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId((int) $this->factory->orderAddress($this->buyerCountry())->getId())
            ->setInvoiceOrderAddressId((int) $this->factory->orderAddress($this->buyerCountry())->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage('0')
            ->setPostageTax('0');
        // Read again, as the checkout hands it over: the lines are read by the placement.
        $cart = CartQuery::create()->findPk($cart->getId());

        return fn () => $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $currency,
            $lang,
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
            recordConsentAnswers: false,
        );
    }
}
