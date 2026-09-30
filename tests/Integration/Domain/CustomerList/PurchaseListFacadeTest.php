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

namespace Thelia\Tests\Integration\Domain\CustomerList;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CustomerList\PurchaseListEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\Enum\CustomerListType;
use Thelia\Domain\CustomerList\Exception\InvalidPurchaseListException;
use Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException;
use Thelia\Domain\CustomerList\Exception\PurchaseListSourceNotFoundException;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class PurchaseListFacadeTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private PurchaseListFacade $facade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->facade = $this->getService(PurchaseListFacade::class);
    }

    public function testTheLinesOfEveryListAreCountedInOneQuery(): void
    {
        $customer = $this->customer();
        $full = $this->facade->create($customer, 'Full', self::lines(['A-1' => 1, 'B-2' => 2, 'C-3' => 3]));
        $empty = $this->facade->create($customer, 'Empty');
        $few = [$full, $empty];
        $many = [$full, $empty];

        for ($n = 1; $n <= 20; ++$n) {
            $many[] = $this->facade->create($customer, 'List '.$n, self::lines(['REF-'.$n => 1]));
        }

        $counts = $this->facade->countItemsOf($few);

        self::assertSame([(int) $full->getId() => 3], $counts);
        self::assertCount(1, $this->recordSqlQueries(fn () => $this->facade->countItemsOf($few)));
        self::assertCount(1, $this->recordSqlQueries(fn () => $this->facade->countItemsOf($many)));
        self::assertSame([], $this->facade->countItemsOf([]));
    }

    public function testACreatedListKeepsItsLinesInOrderAndIsPersonal(): void
    {
        $customer = $this->customer();

        $list = $this->facade->create($customer, '  Weekly   restock ', self::lines(['B-2' => 3, 'A-1' => 1]));

        self::assertSame('Weekly restock', $list->getTitle());
        self::assertSame(CustomerListType::Purchase, $list->getListType());
        self::assertFalse($list->getShared());
        self::assertSame([['B-2', 3], ['A-1', 1]], $this->storedLines($list));
        self::assertFalse($this->facade->canShare($customer, $list));
    }

    public function testACustomerOnlySeesTheirOwnLists(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $mine = $this->facade->create($owner, 'Mine');
        $this->facade->create($other, 'Theirs');

        self::assertSame([$mine->getId()], array_map(static fn (CustomerList $list): int => (int) $list->getId(), $this->facade->listVisibleFor($owner)));
    }

    public function testTheListOfAnotherCustomerAnswersAsIfItDidNotExist(): void
    {
        $list = $this->facade->create($this->customer(), 'Not yours');
        $intruder = $this->customer();

        foreach ([
            fn () => $this->facade->getVisible($intruder, (int) $list->getId()),
            fn () => $this->facade->rename($intruder, (int) $list->getId(), 'Taken'),
            fn () => $this->facade->replaceItems($intruder, (int) $list->getId(), self::lines(['X' => 1])),
            fn () => $this->facade->duplicate($intruder, (int) $list->getId()),
            fn () => $this->facade->delete($intruder, (int) $list->getId()),
            fn () => $this->facade->linesToLoad($intruder, (int) $list->getId()),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Another customer reached the list.');
            } catch (PurchaseListNotFoundException) {
            }
        }

        self::assertSame('Not yours', CustomerListQuery::create()->findPk($list->getId())?->getTitle());
    }

    public function testRenamingAndEditingTheLines(): void
    {
        $customer = $this->customer();
        $list = $this->facade->create($customer, 'Draft', self::lines(['A' => 1, 'B' => 2]));

        $this->facade->rename($customer, (int) $list->getId(), 'Final');
        $this->facade->appendItems($customer, (int) $list->getId(), self::lines(['C' => 5, 'A' => 4]));

        self::assertSame('Final', CustomerListQuery::create()->findPk($list->getId())?->getTitle());
        self::assertSame([['A', 5], ['B', 2], ['C', 5]], $this->storedLines($list));

        $this->facade->replaceItems($customer, (int) $list->getId(), self::lines(['Z' => 9]));

        self::assertSame([['Z', 9]], $this->storedLines($list));
    }

    public function testDeletingAListDeletesItsLines(): void
    {
        $customer = $this->customer();
        $list = $this->facade->create($customer, 'Gone', self::lines(['A' => 1]));

        $this->facade->delete($customer, (int) $list->getId());

        self::assertNull(CustomerListQuery::create()->findPk($list->getId()));
        self::assertSame(0, CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->count());
    }

    public function testADuplicateIsANewListWithTheSameLines(): void
    {
        $customer = $this->customer();
        $source = $this->facade->create($customer, 'Original', self::lines(['A' => 1, 'B' => 2]));

        $copy = $this->facade->duplicate($customer, (int) $source->getId(), 'Copy');

        self::assertNotSame($source->getId(), $copy->getId());
        self::assertSame('Copy', $copy->getTitle());
        self::assertSame($this->storedLines($source), $this->storedLines($copy));
    }

    public function testACustomerKeepsAtMostAHundredLists(): void
    {
        $customer = $this->customer();

        for ($n = 1; $n <= PurchaseListFacade::MAX_LISTS_PER_CUSTOMER; ++$n) {
            (new CustomerList())
                ->setCustomerId((int) $customer->getId())
                ->setType(CustomerListType::Purchase->value)
                ->setTitle('List '.$n)
                ->save($this->getPropelConnection());
        }

        $this->expectException(InvalidPurchaseListException::class);

        $this->facade->create($customer, 'One too many');
    }

    public function testAnEmptyTitleIsRefused(): void
    {
        $this->expectException(InvalidPurchaseListException::class);

        $this->facade->create($this->customer(), "  \t ");
    }

    public function testAListCreatedFromAnOrderTakesItsReferencesAndQuantities(): void
    {
        $customer = $this->customer();
        [$product, $kept] = $this->productWithSaleElement('KEPT');
        [, $removed] = $this->productWithSaleElement('REMOVED');
        $order = $this->factory->order($customer);
        $this->orderLine((int) $order->getId(), $product, $kept, 3.0);
        $this->orderLine((int) $order->getId(), $product, $removed, 2.0);
        $removed->delete($this->getPropelConnection());

        $list = $this->facade->createFromOrder($customer, (int) $order->getId(), 'From my order');

        self::assertSame([['KEPT', 3], ['REMOVED', 2]], $this->storedLines($list));
        self::assertSame(
            [(int) $kept->getId(), null],
            array_map(static fn (ReferenceQuantity $line): ?int => $line->productSaleElementsId, $this->facade->linesToLoad($customer, (int) $list->getId())),
        );
    }

    public function testTheOrderOfAnotherCustomerAnswersAsIfItDidNotExist(): void
    {
        $order = $this->factory->order($this->customer());

        $this->expectException(PurchaseListSourceNotFoundException::class);

        $this->facade->createFromOrder($this->customer(), (int) $order->getId(), 'Not my order');
    }

    public function testAListCreatedFromTheCartTakesItsLines(): void
    {
        $customer = $this->customer();
        [$product, $saleElements] = $this->productWithSaleElement('IN-CART');
        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product, $saleElements, ['quantity' => 4.0]);

        $list = $this->facade->createFromCart($customer, $cart, 'From my cart');

        self::assertSame([['IN-CART', 4]], $this->storedLines($list));
    }

    public function testTheCartOfAnotherCustomerIsRefused(): void
    {
        $cart = $this->factory->cart($this->customer());

        $this->expectException(PurchaseListSourceNotFoundException::class);

        $this->facade->createFromCart($this->customer(), $cart, 'Not my cart');
    }

    public function testALineWhoseSaleElementLeftTheCatalogStaysWithItsReference(): void
    {
        $customer = $this->customer();
        [, $saleElements] = $this->productWithSaleElement('SOON-GONE');
        $list = $this->facade->create($customer, 'Old list', new ReferenceQuantityLines([
            new ReferenceQuantity('SOON-GONE', 2, (int) $saleElements->getId()),
        ]));

        $saleElements->delete($this->getPropelConnection());

        $lines = $this->facade->linesToLoad($customer, (int) $list->getId());
        self::assertCount(1, $lines);
        self::assertSame('SOON-GONE', $lines[0]->reference);
        self::assertNull($lines[0]->productSaleElementsId);
    }

    public function testDeletingTheCustomerDeletesTheirLists(): void
    {
        $customer = $this->customer();
        $list = $this->facade->create($customer, 'Mine', self::lines(['A' => 1]));

        $customer->delete($this->getPropelConnection());

        self::assertNull(CustomerListQuery::create()->findPk($list->getId()));
        self::assertSame(0, CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->count());
    }

    public function testEachWriteIsDispatchedForModulesToReactTo(): void
    {
        $seen = [];
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        foreach ([TheliaEvents::PURCHASE_LIST_CREATE, TheliaEvents::PURCHASE_LIST_UPDATE, TheliaEvents::PURCHASE_LIST_DELETE] as $eventName) {
            $dispatcher->addListener($eventName, static function (PurchaseListEvent $event) use (&$seen, $eventName): void {
                $seen[] = $eventName;
            }, 0);
        }

        $customer = $this->customer();
        $list = $this->facade->create($customer, 'Watched');
        $this->facade->rename($customer, (int) $list->getId(), 'Renamed');
        $this->facade->delete($customer, (int) $list->getId());

        self::assertSame([TheliaEvents::PURCHASE_LIST_CREATE, TheliaEvents::PURCHASE_LIST_UPDATE, TheliaEvents::PURCHASE_LIST_DELETE], $seen);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * @param array<string, int> $quantities
     */
    private static function lines(array $quantities): ReferenceQuantityLines
    {
        $lines = [];

        foreach ($quantities as $reference => $quantity) {
            $lines[] = new ReferenceQuantity((string) $reference, $quantity);
        }

        return new ReferenceQuantityLines($lines);
    }

    /**
     * @return list<array{string, int}>
     */
    private function storedLines(CustomerList $list): array
    {
        $lines = [];

        foreach (CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->orderByPosition()->find() as $item) {
            $lines[] = [(string) $item->getRef(), (int) $item->getQuantity()];
        }

        return $lines;
    }

    /**
     * @return array{Product, ProductSaleElements}
     */
    private function productWithSaleElement(string $reference): array
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return [$product, $this->factory->productSaleElement($product, ['ref' => $reference])];
    }

    private function orderLine(int $orderId, Product $product, ProductSaleElements $saleElements, float $quantity): void
    {
        (new OrderProduct())
            ->setOrderId($orderId)
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($saleElements->getRef())
            ->setProductSaleElementsId($saleElements->getId())
            ->setTitle('Ordered line')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());
    }
}
