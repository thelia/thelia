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

namespace Thelia\Tests\Unit\Condition;

use Thelia\Condition\Exception\UnmatchableConditionException;
use Thelia\Condition\Implementation\MatchDeliveryCountries;
use Thelia\Condition\Operators;
use Thelia\Model\Address;
use Thelia\Model\Customer;

/**
 * The delivery country condition matches a cart by the country of its delivery address.
 */
final class MatchDeliveryCountriesTest extends FacadeBackedTestCase
{
    public function testACartDeliveredToOneOfTheCountriesMatches(): void
    {
        self::assertTrue($this->conditionDeliveringTo(64, Operators::IN, [64, 75])->isMatching());
    }

    public function testACartDeliveredToAnotherCountryDoesNotMatch(): void
    {
        self::assertFalse($this->conditionDeliveringTo(13, Operators::IN, [64, 75])->isMatching());
    }

    public function testTheNotInOperatorMatchesACartDeliveredOutsideTheCountries(): void
    {
        self::assertTrue($this->conditionDeliveringTo(13, Operators::OUT, [64, 75])->isMatching());
    }

    public function testACartWithoutADeliveryAddressCannotBeChecked(): void
    {
        $this->expectException(UnmatchableConditionException::class);

        $this->conditionDeliveringTo(null, Operators::IN, [64])->isMatching();
    }

    /**
     * @param list<int> $countryIds
     */
    private function conditionDeliveringTo(?int $countryId, string $operator, array $countryIds): MatchDeliveryCountries
    {
        $facade = $this->makeFacade();
        $facade->method('getCustomer')->willReturn(new Customer());
        $facade->method('getDeliveryAddress')->willReturn(null === $countryId ? null : (new Address())->setCountryId($countryId));

        return (new MatchDeliveryCountries($facade))->setValidatorsFromForm(
            [MatchDeliveryCountries::COUNTRIES_LIST => $operator],
            [MatchDeliveryCountries::COUNTRIES_LIST => $countryIds],
        );
    }
}
