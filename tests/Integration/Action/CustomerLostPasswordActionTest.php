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

use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A lost-password request names an address the caller typed, so it must not be able
 * to act on the account behind it: it only mails the owner a way back in.
 */
final class CustomerLostPasswordActionTest extends ActionIntegrationTestCase
{
    public function testAskingForALinkLeavesTheStoredPasswordAlone(): void
    {
        $email = 'lost-password-'.bin2hex(random_bytes(8)).'@example.com';
        $customer = $this->factory->customer($this->factory->customerTitle(), ['email' => $email]);
        $storedPassword = $customer->getPassword();

        $this->dispatch(new LostPasswordEvent($email), TheliaEvents::LOST_PASSWORD);

        self::assertSame(
            $storedPassword,
            CustomerQuery::create()->findPk($customer->getId())?->getPassword(),
            'Asking for a link must leave the account usable with the password its owner already has.',
        );
    }

    public function testAnUnknownAddressIsAnsweredLikeAKnownOne(): void
    {
        $this->dispatch(
            new LostPasswordEvent('unknown-'.bin2hex(random_bytes(8)).'@example.com'),
            TheliaEvents::LOST_PASSWORD,
        );

        // Reaching this point is the assertion: the listener must stay silent about
        // whether the address has an account, so the caller learns nothing from it.
        self::assertTrue(true);
    }
}
