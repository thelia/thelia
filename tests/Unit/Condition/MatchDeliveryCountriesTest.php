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

use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\MatchDeliveryCountries;
use Thelia\Condition\Operators;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * A country condition needs at least one country, and says which condition refused the form.
 */
final class MatchDeliveryCountriesTest extends FacadeBackedTestCase
{
    use SilencedTlog;

    protected function setUp(): void
    {
        $this->silenceTlog();
    }

    protected function tearDown(): void
    {
        $this->restoreTlog();
    }

    public function testAConditionWithoutAnyCountryIsRefusedUnderItsOwnName(): void
    {
        $this->expectException(InvalidConditionValueException::class);
        $this->expectExceptionMessage('Invalid Parameter for Condition '.MatchDeliveryCountries::class.' on parameter countries');

        (new MatchDeliveryCountries(parent::makeFacade()))->setValidatorsFromForm(
            [MatchDeliveryCountries::COUNTRIES_LIST => Operators::IN],
            [MatchDeliveryCountries::COUNTRIES_LIST => []],
        );
    }
}
