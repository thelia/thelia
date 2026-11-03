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

namespace Thelia\Tests\Integration\Customer;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerGroupChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * TheliaEvents::CUSTOMER_GROUP_CHANGED is "sent once a customer is moved to another group and
 * the move is written". Deleting a group moves its customers to the default group: each of
 * them is moved, so each move is announced, as CustomerGroupAssignment does for any other move.
 */
final class CustomerGroupDeletionAnnouncesTheMoveTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<CustomerGroupChangedEvent> */
    private array $heard = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::CUSTOMER_GROUP_CHANGED,
            function (CustomerGroupChangedEvent $event): void {
                $this->heard[] = $event;
            },
        );
    }

    public function testDeletingAGroupAnnouncesTheMoveOfEachOfItsCustomersToTheDefaultGroup(): void
    {
        $default = CustomerGroupQuery::findDefault();
        $pro = $this->factory->customerGroup('v6-deleted-group');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);
        $proId = (int) $pro->getId();

        $pro->delete();

        CustomerTableMap::clearInstancePool();
        self::assertSame((int) $default->getId(), (int) CustomerQuery::create()->findPk($customer->getId())->getCustomerGroupId());

        self::assertCount(1, $this->heard, 'The customer moved to the default group, and the move was not announced.');
        self::assertSame((int) $customer->getId(), (int) $this->heard[0]->getCustomer()->getId());
        self::assertSame($proId, $this->heard[0]->getPreviousCustomerGroupId());
        self::assertSame((int) $default->getId(), $this->heard[0]->getCustomerGroupId());
    }
}
