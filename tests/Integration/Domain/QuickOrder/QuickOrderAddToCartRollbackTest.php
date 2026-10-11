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

namespace Thelia\Tests\Integration\Domain\QuickOrder;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\QuickOrder\QuickOrderFacade;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Category;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The addition to the cart is one transaction. A test wrapped in its own
 * transaction cannot see that: Propel has no savepoints, so the inner rollback
 * only waits for the outer one. This test runs without it and removes what it
 * created itself.
 */
final class QuickOrderAddToCartRollbackTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    /** @var list<object> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $record) {
            if (method_exists($record, 'delete') && method_exists($record, 'isDeleted') && !$record->isDeleted()) {
                $record->delete();
            }
        }

        parent::tearDown();
    }

    public function testALineThatFailsToReachTheCartTakesTheOthersBackOut(): void
    {
        $factory = $this->createFixtureFactory();
        // The currency and the tax rule are the shop's own, shared by every test:
        // only what this test creates is removed afterwards.
        $currency = $factory->currency();
        $taxRule = $factory->taxRule();
        $category = $this->keep($factory->category());
        $customer = $this->keep($factory->customer($factory->customerTitle()));
        $first = $this->keep($factory->product($category, $taxRule, $currency, ['baseQuantity' => 50]));
        $failing = $this->keep($factory->product($category, $taxRule, $currency, ['baseQuantity' => 50]));
        $cart = $this->keep($factory->cart($customer, ['currency' => $currency]));
        $failingSaleElementsId = (int) $failing->getProductSaleElementss()->getFirst()?->getId();

        static::getContainer()->get('event_dispatcher')->addListener(
            TheliaEvents::CART_ADDITEM,
            static function (CartEvent $event) use ($failingSaleElementsId): void {
                if ($event->getProductSaleElementsId() === $failingSaleElementsId) {
                    throw new \RuntimeException('The cart refused this line.');
                }
            },
            256,
        );

        try {
            $this->getService(QuickOrderFacade::class)->addToCart($customer, $cart, new ReferenceQuantityLines([
                new ReferenceQuantity((string) $first->getRef(), 1),
                new ReferenceQuantity((string) $failing->getRef(), 1),
            ]));
            self::fail('The failing line did not stop the addition.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The cart refused this line.', $exception->getMessage());
        }

        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count(), 'The line added before the failure stayed in the cart.');
    }

    /**
     * @template T of Category|Customer|Product|\Thelia\Model\Cart
     *
     * @param T $record
     *
     * @return T
     */
    private function keep(object $record): object
    {
        $this->created[] = $record;

        return $record;
    }
}
