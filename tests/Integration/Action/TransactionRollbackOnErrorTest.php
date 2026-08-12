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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Action\Address as AddressAction;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerTitleService;
use Thelia\Model\CustomerTitle;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The actions wrap their writes in a Propel transaction and roll it back from a
 * catch block. When that catch was narrower than \Throwable, an Error thrown by
 * the code inside the try (a TypeError, a call on null, a module listener
 * blowing up) escaped with the transaction still open: the connection kept its
 * row and metadata locks, and every later write on the same tables waited for
 * the lock timeout.
 *
 * The nesting counter of the Propel connection is what makes this observable.
 * IntegrationTestCase opens one transaction per test, so the counter sits at 1
 * when the action is called; the action's own beginTransaction() takes it to 2.
 * If the counter is still 2 once the error has propagated, the transaction
 * leaked.
 */
final class TransactionRollbackOnErrorTest extends ActionIntegrationTestCase
{
    public function testAddressCreateRollsBackWhenANonPropelErrorEscapes(): void
    {
        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);
        $country = $this->factory->country();

        $connection = Propel::getWriteConnection(AddressTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);

        $nestingBeforeTheAction = $connection->getNestedTransactionCount();

        // The event carries no title, so the action asks the service for the
        // default one — from inside its transaction. A service blowing up with
        // an Error stands for any non-Exception failure in that block.
        $action = new AddressAction(new class extends CustomerTitleService {
            public function __construct()
            {
            }

            public function getDefaultCustomerTitle(): ?CustomerTitle
            {
                throw new \Error('Error raised inside the action transaction');
            }
        });

        $event = new AddressCreateOrUpdateEvent(
            label: 'Home',
            title: null,
            firstname: 'John',
            lastname: 'Doe',
            address1: '10 rue de la Paix',
            address2: '',
            address3: '',
            zipcode: '75002',
            city: 'Paris',
            country: $country->getId(),
            cellphone: '',
            phone: '',
            company: null,
        );
        $event->setCustomer($customer);

        try {
            $action->create($event, TheliaEvents::ADDRESS_CREATE, $this->dispatcher);

            self::fail('The Error raised by the service should have propagated out of the action.');
        } catch (\Error $error) {
            // The error must still reach the caller unchanged: the fix only adds
            // the rollback, it does not swallow anything.
            self::assertSame('Error raised inside the action transaction', $error->getMessage());
        }

        self::assertSame(
            $nestingBeforeTheAction,
            $connection->getNestedTransactionCount(),
            'The action left its transaction open: every later write on address will now wait for the lock timeout.',
        );
    }
}
