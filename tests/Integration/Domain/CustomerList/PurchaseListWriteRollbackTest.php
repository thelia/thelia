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

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListItem;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\Event\CustomerListItemEvent;
use Thelia\Test\IntegrationTestCase;

/**
 * A list and its lines are written together. Replacing the lines deletes the old
 * ones first: a failure halfway must leave the list as it was, not empty. A test
 * wrapped in its own transaction cannot see that, Propel having no savepoints, so
 * this one runs without it and removes what it created itself.
 */
final class PurchaseListWriteRollbackTest extends IntegrationTestCase
{
    private const string FAILING_REFERENCE = 'REFUSED-BY-THE-DATABASE';

    protected bool $useTransaction = false;

    private ?Customer $customer = null;

    protected function tearDown(): void
    {
        if (null !== $this->customer) {
            CustomerListQuery::create()->filterByCustomerId($this->customer->getId())->delete();
            $this->customer->delete();
        }

        parent::tearDown();
    }

    public function testALineThatFailsToBeWrittenLeavesTheListAsItWas(): void
    {
        $factory = $this->createFixtureFactory();
        $this->customer = $factory->customer($factory->customerTitle());
        $facade = $this->getService(PurchaseListFacade::class);
        $list = $facade->create($this->customer, 'Kept', self::lines('FIRST', 'SECOND'));
        $this->failOnTheLine(self::FAILING_REFERENCE);

        try {
            $facade->replaceItems($this->customer, (int) $list->getId(), self::lines('NEW', self::FAILING_REFERENCE));
            self::fail('The failing line did not stop the write.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The database refused this line.', $exception->getMessage());
        }

        self::assertSame(['FIRST', 'SECOND'], $this->referencesOf($list), 'The old lines were deleted and the new ones never written.');
    }

    public function testAListWhoseLinesFailToBeWrittenIsNotCreated(): void
    {
        $factory = $this->createFixtureFactory();
        $this->customer = $factory->customer($factory->customerTitle());
        $this->failOnTheLine(self::FAILING_REFERENCE);

        try {
            $this->getService(PurchaseListFacade::class)->create($this->customer, 'Never', self::lines('NEW', self::FAILING_REFERENCE));
            self::fail('The failing line did not stop the creation.');
        } catch (\RuntimeException) {
        }

        self::assertSame(0, CustomerListQuery::create()->filterByCustomerId($this->customer->getId())->count(), 'A list was left without its lines.');
    }

    private function failOnTheLine(string $reference): void
    {
        static::getContainer()->get('event_dispatcher')->addListener(
            CustomerListItemEvent::PRE_INSERT,
            static function (CustomerListItemEvent $event) use ($reference): void {
                $item = $event->getModel();

                if ($item instanceof CustomerListItem && $reference === $item->getRef()) {
                    throw new \RuntimeException('The database refused this line.');
                }
            },
        );
    }

    private static function lines(string ...$references): ReferenceQuantityLines
    {
        return new ReferenceQuantityLines(array_map(static fn (string $reference): ReferenceQuantity => new ReferenceQuantity($reference, 1), $references));
    }

    /**
     * @return list<string>
     */
    private function referencesOf(CustomerList $list): array
    {
        return array_map(
            static fn ($item): string => (string) $item->getRef(),
            CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->orderByPosition()->find()->getData(),
        );
    }
}
