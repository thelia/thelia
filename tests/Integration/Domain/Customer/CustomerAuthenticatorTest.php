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

namespace Thelia\Tests\Integration\Domain\Customer;

use Thelia\Domain\Customer\Exception\CustomerNotEnabledException;
use Thelia\Domain\Customer\Service\CustomerAuthenticator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

/**
 * What closes an account at sign-in time.
 *
 * The shop's "confirm every address" setting decides, as it always has. The guest
 * checkout does not change that: a converted guest is kept out by its row still being a
 * guest row — see {@see GuestRowIsNotAnAccountTest} — and never reaches this point,
 * whatever the setting says.
 */
final class CustomerAuthenticatorTest extends IntegrationTestCase
{
    public function testAnAccountTheShopHasNotEnabledIsClosedWhenItConfirmsAddresses(): void
    {
        $this->withEmailConfirmation(true, function (): void {
            $customer = $this->customerWaitingForItsCode();

            $this->expectException(CustomerNotEnabledException::class);

            $this->getService(CustomerAuthenticator::class)->processLogin($customer);
        });
    }

    /**
     * The `enable` column defaults to 0 and a shop that confirms nothing leaves it there.
     * On such a shop an account signs in, code waiting or not: shops carry accounts with
     * a token left over from a registration nobody finished, and reading it here would
     * lock out people the shop considers customers.
     */
    public function testAnAccountSignsInWhenTheShopConfirmsNothing(): void
    {
        $this->withEmailConfirmation(false, function (): void {
            $this->getService(CustomerAuthenticator::class)->processLogin($this->customerWaitingForItsCode());

            $this->expectNotToPerformAssertions();
        });
    }

    private function customerWaitingForItsCode(): Customer
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setEnable(0);
        $customer->setConfirmationTokenWithExpiry();
        $customer->save();

        return $customer;
    }

    private function withEmailConfirmation(bool $enabled, callable $test): void
    {
        $wasEnabled = ConfigQuery::isCustomerEmailConfirmationEnable();
        ConfigQuery::write('customer_email_confirmation', $enabled ? '1' : '0');

        try {
            $test();
        } finally {
            ConfigQuery::write('customer_email_confirmation', $wasEnabled ? '1' : '0');
        }
    }
}
