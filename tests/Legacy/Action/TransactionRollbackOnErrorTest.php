<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Action\Address;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Model\Address as AddressModel;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\AddressTableMap;

/**
 * The actions wrap their writes in a Propel transaction and roll it back from a
 * catch block. When that catch was narrower than \Throwable, an Error thrown by
 * the code inside the try (a TypeError, a call on null, a module listener
 * blowing up) escaped with the transaction still open: the connection kept its
 * row and metadata locks, and every later write on the same tables waited for
 * the lock timeout. That is how a single failure in this suite turned into six.
 *
 * The nesting counter of the Propel connection is what makes this observable:
 * it goes back to its starting value once the transaction has been closed, and
 * stays one level above it when the transaction leaked.
 */
class TransactionRollbackOnErrorTest extends BaseAction
{
    public function testAddressUpdateRollsBackWhenANonPropelErrorEscapes(): void
    {
        $customer = CustomerQuery::create()->findOne();
        $address = $customer->getAddresses()->getFirst();

        $connection = Propel::getWriteConnection(AddressTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);

        $nestingBeforeTheAction = $connection->getNestedTransactionCount();

        // A model whose save() raises an Error stands for any non-Exception
        // failure happening inside the action's transaction.
        $failingAddress = new class() extends AddressModel {
            public function save(ConnectionInterface $con = null): void
            {
                throw new \Error('Error raised inside the action transaction');
            }
        };
        $failingAddress->setId($address->getId());
        $failingAddress->setCustomerId($customer->getId());
        $failingAddress->setNew(false);

        $event = new AddressCreateOrUpdateEvent(
            'test address',
            1,
            'Thelia',
            'Thelia',
            '5 rue rochon',
            '',
            '',
            '63000',
            'clermont-ferrand',
            64,
            '0102030405',
            '',
            ''
        );
        $event->setAddress($failingAddress);

        try {
            (new Address())->update($event, null, $this->getMockEventDispatcher());

            self::fail('The Error raised by save() should have propagated out of the action.');
        } catch (\Error $error) {
            // The error must still reach the caller unchanged: the fix only adds
            // the rollback, it does not swallow anything.
            self::assertSame('Error raised inside the action transaction', $error->getMessage());
        }

        self::assertSame(
            $nestingBeforeTheAction,
            $connection->getNestedTransactionCount(),
            'The action left its transaction open: every later write on address will now wait for the lock timeout.'
        );
    }
}
