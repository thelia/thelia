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

use Thelia\Condition\Exception\InvalidConditionOperatorException;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\MatchDeliveryCountries;
use Thelia\Condition\Operators;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * A country condition keeps the countries chosen in the form, needs at least one of them,
 * and says which condition refused the form.
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

    public function testTheOperatorAndTheCountriesOfTheFormAreKept(): void
    {
        $condition = (new MatchDeliveryCountries(parent::makeFacade()))->setValidatorsFromForm(
            [MatchDeliveryCountries::COUNTRIES_LIST => Operators::OUT],
            [MatchDeliveryCountries::COUNTRIES_LIST => [64, 75]],
        );

        $validators = $condition->getValidators();

        self::assertSame([MatchDeliveryCountries::COUNTRIES_LIST => Operators::OUT], $validators['setOperators']);
        self::assertSame([MatchDeliveryCountries::COUNTRIES_LIST => [64, 75]], $validators['setValues']);
    }

    public function testTheFormOffersTheInAndNotInOperators(): void
    {
        self::assertSame(
            [
                MatchDeliveryCountries::COUNTRIES_LIST => [
                    'availableOperators' => [Operators::IN => 'In', Operators::OUT => 'Not in'],
                    'value' => '',
                    'selectedOperator' => Operators::IN,
                ],
            ],
            (new MatchDeliveryCountries(parent::makeFacade()))->getValidators()['inputs'],
        );
    }

    public function testAFormWithoutTheCountriesFieldIsRefused(): void
    {
        $this->expectException(InvalidConditionValueException::class);

        (new MatchDeliveryCountries(parent::makeFacade()))->setValidatorsFromForm(
            [MatchDeliveryCountries::COUNTRIES_LIST => Operators::IN],
            [],
        );
    }

    public function testAnOperatorOtherThanInOrOutIsRefused(): void
    {
        $this->expectException(InvalidConditionOperatorException::class);

        (new MatchDeliveryCountries(parent::makeFacade()))->setValidatorsFromForm(
            [MatchDeliveryCountries::COUNTRIES_LIST => Operators::SUPERIOR],
            [MatchDeliveryCountries::COUNTRIES_LIST => [64]],
        );
    }

    public function testTheConditionIsNamedThroughTheFacadeTranslator(): void
    {
        self::assertSame('Delivery country', (new MatchDeliveryCountries(parent::makeFacade()))->getName());
    }
}
