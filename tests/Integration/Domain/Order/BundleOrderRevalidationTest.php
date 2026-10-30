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
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Order\Exception\BundleLineNotOrderableException;
use Thelia\Domain\Order\Exception\OrderPlacementConflictException;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\BundleOrderPlanner;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\CheckoutStep;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Order\RecordingStockDecrementer;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * A pack put in the cart while its composition was valid, and a catalogue that moved before
 * the order: the composition is judged again when the order is placed, and at the step of
 * the cart before that, by the judge that let it into the cart. A refusal names the pack,
 * writes nothing and takes nothing from the stock.
 *
 * Also what the stock of an order is taken in: the check of a cart without any pack is the
 * one it always was, line by line, and every order locks the stock rows in one order.
 *
 * The pack of the plan: bike (1, required, stock 7), helmet (2, required, stock 5) and lock
 * (1, optional from 0 to 3, stock 10).
 */
final class BundleOrderRevalidationTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use PlacesBundleOrders;
    use RecordsStatements;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check_available_stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAComponentDeletedAfterThePackWasAddedRefusesTheOrderNamingThePack(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $cart, $line, $buyer] = $this->aCartHoldingThePack();

        // The product of the helmet goes, its sale element with it: the item is left orphaned.
        $example['helmetProduct']->delete();

        $this->assertTheOrderIsRefusedNamingThePack($example, $cart, $line, $buyer);
    }

    public function testARequiredItemAddedAfterThePackWasAddedRefusesTheOrder(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $cart, $line, $buyer] = $this->aCartHoldingThePack();

        $charger = $this->component($this->factory, '15', 10);
        $this->getService(BundleCompositionService::class)->addItem((int) $example['bundle']->getId(), (int) $this->saleElementsOf($charger)->getId(), '1');

        $this->assertTheOrderIsRefusedNamingThePack($example, $cart, $line, $buyer);
        self::assertSame(10.0, $this->stockOfProduct($charger));
    }

    public function testAComponentTakenOfflineAfterThePackWasAddedRefusesTheOrder(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $cart, $line, $buyer] = $this->aCartHoldingThePack();

        $example['helmetProduct']->setVisible(0)->save();

        $this->assertTheOrderIsRefusedNamingThePack($example, $cart, $line, $buyer);
    }

    public function testAQuantityTheNarrowedBoundsNoLongerAllowRefusesTheOrder(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '3']);

        $service->updateItem((int) $example['lock']->getId(), (int) $this->saleElementsOf($example['lockProduct'])->getId(), '1', '0', '2', false);

        $this->assertTheOrderIsRefusedNamingThePack($example, $cart, $line, [$customer, $currency, $lang]);
        self::assertSame(10.0, $this->stockOfProduct($example['lockProduct']));
    }

    public function testTheStepOfTheCartReportsThePackBeforeThePayment(): void
    {
        [$example, $cart, $line] = $this->aCartHoldingThePack();
        $guard = $this->getService(CartGuard::class);

        // Still valid: nothing to report.
        $guard->checkBundleLinesOrderable($cart);

        $example['helmetProduct']->setVisible(0)->save();

        try {
            $guard->checkBundleLinesOrderable($cart);
            self::fail('The cart holds a pack whose helmet is offline.');
        } catch (BundleLineNotOrderableException $refusal) {
            self::assertSame((int) $line->getId(), $refusal->cartItemId);
        }

        $violations = $this->getService(CheckoutValidationService::class)->collectViolations($cart);
        $bundleViolations = array_values(array_filter($violations, static fn ($violation): bool => CheckoutViolationCode::BundleLineNotOrderable->value === $violation->code));

        self::assertCount(1, $bundleViolations, 'The validation of the checkout reports the pack.');
        self::assertSame(CheckoutStep::CODE_CART, $bundleViolations[0]->stepCode);
        self::assertSame(['productReference' => (string) $example['bundle']->getRef(), 'cartItemId' => (int) $line->getId()], $bundleViolations[0]->details);
        self::assertStringContainsString((string) $example['bundle']->getRef(), $bundleViolations[0]->message);
    }

    public function testTheVerdictOfTheOrderIsGivenForEveryPackLineAtOnce(): void
    {
        $planner = $this->getService(BundleOrderPlanner::class);
        [$example, $cart, $line] = $this->aCartHoldingThePack();
        [$second] = [$this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class))];
        $secondLine = $this->addBundleLine($cart, $second['bundle'], [(int) $second['bike']->getId() => '1', (int) $second['helmet']->getId() => '2']);
        $this->addSimpleLine($cart, $this->component($this->factory, '10', 5));
        $cart = CartQuery::create()->findPk($cart->getId());

        self::assertSame([], $planner->refusalsOf($cart->getCartItems()));

        $example['helmetProduct']->setVisible(0)->save();
        $second['bikeProduct']->delete();
        $cart = CartQuery::create()->findPk($cart->getId());
        $refusals = $planner->refusalsOf($cart->getCartItems());

        self::assertSame([(int) $line->getId(), (int) $secondLine->getId()], array_keys($refusals), 'Both packs are refused, not only the first.');
        self::assertSame((string) $example['bundle']->getRef(), $refusals[(int) $line->getId()]->productReference);
        self::assertSame((string) $second['bundle']->getRef(), $refusals[(int) $secondLine->getId()]->productReference);
    }

    public function testTheVerdictOfTheOrderFlagsALineOfAPackLeftWithoutCompositionAndSparesAnOfferedOne(): void
    {
        $planner = $this->getService(BundleOrderPlanner::class);
        [, $cart, $line] = $this->aCartHoldingThePackWithoutComposition();

        self::assertSame([(int) $line->getId()], array_keys($planner->refusalsOf($cart->getCartItems())));

        $line->setIsOffered(1)->save();

        self::assertSame([], $planner->refusalsOf(CartQuery::create()->findPk($cart->getId())->getCartItems()));
    }

    public function testTheProgressionSaysWhyTheCartStepRefusesAPackThatCannotBeOrdered(): void
    {
        $progression = $this->getService(CheckoutProgressionService::class);
        [$example, $cart, $line] = $this->aCartHoldingThePack();
        $progression->forget();

        self::assertNotSame(CheckoutStep::CODE_CART, $progression->firstViolation($cart)?->stepCode, 'A pack that can be ordered stops nothing at the cart step.');

        $example['helmetProduct']->setVisible(0)->save();
        $progression->forget();
        $violation = $progression->firstViolation($cart);

        self::assertNotNull($violation);
        self::assertSame(CheckoutStep::CODE_CART, $violation->stepCode);
        self::assertSame(CheckoutViolationCode::BundleLineNotOrderable->value, $violation->code);
        self::assertSame(['productReference' => (string) $example['bundle']->getRef(), 'cartItemId' => (int) $line->getId()], $violation->details);
        self::assertSame($progression->firstIncompleteStep($cart)?->code, $violation->stepCode, 'The violation is the one of the step the buyer is sent back to.');
    }

    public function testTheProgressionNamesTheEmptyCartAsAViolationOfTheCartStep(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $progression = $this->getService(CheckoutProgressionService::class);
        $progression->forget();

        $violation = $progression->firstViolation($cart);

        self::assertSame(CheckoutStep::CODE_CART, $violation?->stepCode);
        self::assertSame(CheckoutViolationCode::CartEmpty->value, $violation->code);
    }

    public function testACartWithoutAPackPassesTheStepOfTheCart(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $this->component($this->factory, '10', 5));

        $this->getService(CartGuard::class)->checkBundleLinesOrderable($cart);

        $codes = array_map(static fn ($violation): string => $violation->code, $this->getService(CheckoutValidationService::class)->collectViolations($cart));
        self::assertNotContains(CheckoutViolationCode::BundleLineNotOrderable->value, $codes);
    }

    public function testALineOfAPackLeftWithoutCompositionIsNotOrderedAsAProduct(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $cart, $line, $buyer] = $this->aCartHoldingThePackWithoutComposition();

        $this->assertTheOrderIsRefusedNamingThePack($example, $cart, $line, $buyer);
    }

    public function testTheStepOfTheCartRefusesALineOfAPackLeftWithoutComposition(): void
    {
        [, $cart, $line] = $this->aCartHoldingThePackWithoutComposition();

        try {
            $this->getService(CartGuard::class)->checkBundleLinesOrderable($cart);
            self::fail('A pack line with no composition cannot be ordered.');
        } catch (BundleLineNotOrderableException $refusal) {
            self::assertSame((int) $line->getId(), $refusal->cartItemId);
        }
    }

    public function testALineOfAPackThatAPromotionOffersWithoutCompositionStaysOrderable(): void
    {
        [, $cart, $line] = $this->aCartHoldingThePackWithoutComposition();
        $line->setIsOffered(1)->save();

        $this->getService(CartGuard::class)->checkBundleLinesOrderable(CartQuery::create()->findPk($cart->getId()));
        $this->addToAssertionCount(1);
    }

    public function testTheStepOfTheCartAsksNothingOfACartWithoutAPack(): void
    {
        $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $this->component($this->factory, '10', 5));
        $this->addSimpleLine($cart, $this->component($this->factory, '20', 5));
        $cart = CartQuery::create()->findPk($cart->getId());
        self::assertCount(2, $cart->getCartItems());
        $guard = $this->getService(CartGuard::class);

        $statements = $this->statementsDuring(static fn () => $guard->checkBundleLinesOrderable($cart));

        self::assertCount(1, $statements, 'One read of the products of the lines, for the whole cart: none of them is a pack.');
        self::assertStringContainsString('FROM `product`', $statements[0]);
        self::assertStringNotContainsString('product_bundle_item', $statements[0]);
    }

    public function testTheOrderOfACartWithoutAPackReadsNothingOfTheBundles(): void
    {
        $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $this->component($this->factory, '10', 5));
        $this->addSimpleLine($cart, $this->component($this->factory, '20', 5));

        $statements = $this->statementsDuring(fn () => $this->placeOrder($cart, $customer, $currency, $lang));

        self::assertNotSame([], $statements);
        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => str_contains($statement, 'product_bundle_item') || str_contains($statement, 'ProductIsBundle'),
        )), 'Neither the composition of a pack nor the reader of its components is asked.');
        self::assertCount(2, array_filter(
            $statements,
            static fn (string $statement): bool => str_starts_with($statement, 'SELECT') && str_contains($statement, 'FROM `product_sale_elements`'),
        ), 'The stock of each line is read once, as before the packs.');
    }

    /**
     * Stock taken at the payment: the order is only checked at its creation, one line at a
     * time, as it always was. Two lines of the same sale element each within the stock are
     * accepted, whatever they come to together; the payment is what takes the stock.
     */
    public function testTwoLinesOfTheSameSaleElementWithoutAnyPackAreStillCheckedOneAtATime(): void
    {
        $product = $this->component($this->factory, '10', 1);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $product, 1.0, '10.000000');
        $this->addSimpleLine($cart, $product, 1.0, '0.000000');

        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertCount(2, $this->linesOf($order));
        self::assertSame(1.0, $this->stockOfProduct($product), 'The stock is taken at the payment, not at the creation.');
    }

    public function testTheStockOfAnOrderIsTakenInTheAscendingIdOfTheSaleElements(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $service = $this->getService(BundleCompositionService::class);
        // The helmet is created first: its sale element has the lower id.
        $helmet = $this->component($this->factory, '30', 5);
        $bike = $this->component($this->factory, '500', 5);
        $pack = $this->bundleProduct($this->factory, $service);
        $helmetItem = $service->addItem((int) $pack->getId(), (int) $this->saleElementsOf($helmet)->getId(), '1');

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        // The bike comes first in the cart, the helmet of the pack second.
        $this->addSimpleLine($cart, $bike, 1.0);
        $this->addBundleLine($cart, $pack, [(int) $helmetItem->getId() => '1']);

        $decrementer = new RecordingStockDecrementer($this->getService(SaleUnitStockResolver::class));
        $this->placeOrderWith($decrementer, $cart, $customer, $currency, $lang);

        self::assertSame(
            [(int) $this->saleElementsOf($helmet)->getId(), (int) $this->saleElementsOf($bike)->getId()],
            $decrementer->decrementedIds(),
            'The rows are locked by ascending id, not in the order of the lines.',
        );
    }

    public function testADeadlockBrokenByTheDatabaseIsARefusal(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [, $cart, , [$customer, $currency, $lang]] = $this->aCartHoldingThePack();

        $deadlock = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock');
        $deadlock->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
        $decrementer = new RecordingStockDecrementer($this->getService(SaleUnitStockResolver::class), failOnCall: 2, failure: $deadlock);

        try {
            $this->placeOrderWith($decrementer, $cart, $customer, $currency, $lang);
            self::fail('A deadlock refuses the order.');
        } catch (OrderPlacementConflictException $conflict) {
            self::assertSame($deadlock, $conflict->getPrevious());
        }

        // What the rollback undoes is not visible here, inside the transaction the test case
        // wraps around each test; BundleOrderConcurrencyTest sees it on committed rows.
    }

    public function testAnyOtherFailureOfTheDatabaseIsNotTurnedIntoARefusal(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [, $cart, , [$customer, $currency, $lang]] = $this->aCartHoldingThePack();

        $failure = new \PDOException('SQLSTATE[42S02]: Base table or view not found');
        $failure->errorInfo = ['42S02', 1146, 'Table does not exist'];
        $decrementer = new RecordingStockDecrementer($this->getService(SaleUnitStockResolver::class), failOnCall: 1, failure: $failure);

        $this->expectExceptionObject($failure);

        $this->placeOrderWith($decrementer, $cart, $customer, $currency, $lang);
    }

    /**
     * @param array<string, mixed>                                                               $example
     * @param array{0: \Thelia\Model\Customer, 1: \Thelia\Model\Currency, 2: \Thelia\Model\Lang} $buyer
     */
    private function assertTheOrderIsRefusedNamingThePack(array $example, Cart $cart, CartItem $line, array $buyer): void
    {
        [$customer, $currency, $lang] = $buyer;
        $bikeStock = $this->stockOfProduct($example['bikeProduct']);

        try {
            $this->placeOrder($cart, $customer, $currency, $lang);
            self::fail('The pack can no longer be ordered as it is in the cart.');
        } catch (BundleLineNotOrderableException $refusal) {
            self::assertSame((string) $example['bundle']->getRef(), $refusal->productReference, 'The refusal names the pack.');
            self::assertSame((int) $line->getId(), $refusal->cartItemId);
            self::assertStringContainsString((string) $example['bundle']->getRef(), $refusal->getMessage());
            self::assertInstanceOf(InvalidBundleCompositionException::class, $refusal->getPrevious());
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count(), 'Nothing is written.');
        self::assertSame($bikeStock, $this->stockOfProduct($example['bikeProduct']), 'Nothing is taken from the stock.');
        self::assertSame(7.0, $bikeStock);
    }

    /**
     * @return array{0: array<string, mixed>, 1: Cart, 2: CartItem, 3: array{0: \Thelia\Model\Customer, 1: \Thelia\Model\Currency, 2: \Thelia\Model\Lang}}
     */
    private function aCartHoldingThePackWithoutComposition(): array
    {
        [$example, $cart, $line, $buyer] = $this->aCartHoldingThePack();

        // What a line written before the product became a bundle looks like: the model refuses to save one now.
        CartItemQuery::create()->filterById($line->getId())->update(['BundleComposition' => null]);

        return [$example, CartQuery::create()->findPk($cart->getId()), CartItemQuery::create()->findPk($line->getId()), $buyer];
    }

    /**
     * @return array{0: array<string, mixed>, 1: Cart, 2: CartItem, 3: array{0: \Thelia\Model\Customer, 1: \Thelia\Model\Currency, 2: \Thelia\Model\Lang}}
     */
    private function aCartHoldingThePack(): array
    {
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);

        return [$example, $cart, $line, [$customer, $currency, $lang]];
    }

    /**
     * The facade of the container, with every collaborator it has but the stock decrementer.
     */
    private function placeOrderWith(RecordingStockDecrementer $decrementer, Cart $cart, \Thelia\Model\Customer $customer, \Thelia\Model\Currency $currency, \Thelia\Model\Lang $lang): void
    {
        $real = $this->getService(OrderFacade::class);
        $collaborators = (fn (): array => get_object_vars($this))->call($real);
        $collaborators['stockDecrementer'] = $decrementer;
        $facade = new OrderFacade(...$collaborators);

        $deliveryModule = \Thelia\Model\ModuleQuery::create()->findOneByCode('CustomDelivery');
        $paymentModule = \Thelia\Model\ModuleQuery::create()->findOneByCode('Cheque');
        $sessionOrder = (new \Thelia\Model\Order())
            ->setDeliveryOrderAddressId($this->factory->orderAddress($this->buyerCountry())->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress($this->buyerCountry())->getId())
            ->setStatusId(\Thelia\Model\OrderStatusQuery::getNotPaidStatus()->getId())
            ->setDeliveryModuleId($deliveryModule?->getId())
            ->setPaymentModuleId($paymentModule?->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        $facade->createOrder($this->dispatcher, $sessionOrder, $currency, $lang, $cart, $customer, useOrderDefinedAddresses: true, recordConsentAnswers: false);
    }

    private function manageTheStockWhenTheOrderIsPlaced(): void
    {
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');

        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
