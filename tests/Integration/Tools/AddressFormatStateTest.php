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

namespace Thelia\Tests\Integration\Tools;

use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\AddressFormat;

/**
 * A state stored on an address belongs to the address, not to a country setting:
 * a shop that makes the state optional (`has_states = 0`) still has to see the
 * state it let the customer pick on the formatted address.
 */
final class AddressFormatStateTest extends IntegrationTestCase
{
    public function testAStoredStateIsRenderedWhenTheCountryDoesNotRequireOne(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('USA');
        self::assertNotNull($country, 'the seeded countries are missing from the test database');

        // The shop made the state optional, the customer picked one anyway.
        $country->setHasStates(0)->save();

        $state = StateQuery::create()
            ->filterByCountryId($country->getId())
            ->filterByIsocode('CA')
            ->findOne();
        self::assertNotNull($state, 'California is missing from the seeded states');

        $address = $this->createFixtureFactory()
            ->orderAddress($country, null, ['city' => 'Mountain View', 'zipcode' => '94043']);
        $address->setStateId($state->getId())->save();

        $formatted = AddressFormat::getInstance()->formatTheliaAddress($address, 'en_US', html: false);

        self::assertStringContainsString('Mountain View, US-CA 94043', $formatted);
    }
}
