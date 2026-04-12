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

namespace Thelia\Tests\Integration\Condition;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Exception\InvalidConditionOperatorException;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\CartContainsProducts;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Operators;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Currency;
use Thelia\Test\IntegrationTestCase;

/**
 * Integration-level tests for condition failure paths that construct
 * Tlog::getInstance() (and thus ConfigQuery) in their exception
 * constructors. These cannot run as pure unit tests.
 *
 * @see session-handover.md §4.7 for rationale
 */
final class ConditionFailurePathTest extends IntegrationTestCase
{
    private function makeFacade(array $currencies = []): FacadeInterface
    {
        $translator = Translator::getInstance();

        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());

        if ([] !== $currencies) {
            $facade->method('getAvailableCurrencies')->willReturn($currencies);
        }

        return $facade;
    }

    private function facadeWithEUR(): FacadeInterface
    {
        $eur = new Currency();
        $eur->setCode('EUR');

        return $this->makeFacade([$eur]);
    }

    public function testMatchForTotalAmountThrowsOnInvalidPrice(): void
    {
        $condition = new MatchForTotalAmount($this->facadeWithEUR());

        $this->expectException(InvalidConditionValueException::class);

        $condition->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => -10.0,
                MatchForTotalAmount::CART_CURRENCY => 'EUR',
            ],
        );
    }

    public function testMatchForTotalAmountThrowsOnInvalidCurrency(): void
    {
        $condition = new MatchForTotalAmount($this->facadeWithEUR());

        $this->expectException(InvalidConditionValueException::class);

        $condition->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => 100.0,
                MatchForTotalAmount::CART_CURRENCY => 'INVALID',
            ],
        );
    }

    public function testMatchForTotalAmountThrowsOnInvalidOperator(): void
    {
        $condition = new MatchForTotalAmount($this->facadeWithEUR());

        $this->expectException(InvalidConditionOperatorException::class);

        $condition->setValidatorsFromForm(
            [
                MatchForTotalAmount::CART_TOTAL => 'NOT_AN_OPERATOR',
                MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL,
            ],
            [
                MatchForTotalAmount::CART_TOTAL => 100.0,
                MatchForTotalAmount::CART_CURRENCY => 'EUR',
            ],
        );
    }

    public function testMatchForXArticlesThrowsOnInvalidQuantity(): void
    {
        $condition = new MatchForXArticles($this->makeFacade());

        $this->expectException(InvalidConditionValueException::class);

        $condition->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR],
            [MatchForXArticles::CART_QUANTITY => -5],
        );
    }

    public function testMatchForXArticlesThrowsOnInvalidOperator(): void
    {
        $condition = new MatchForXArticles($this->makeFacade());

        $this->expectException(InvalidConditionOperatorException::class);

        $condition->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => 'INVALID'],
            [MatchForXArticles::CART_QUANTITY => 10],
        );
    }

    public function testCartContainsProductsThrowsOnEmptyProductList(): void
    {
        $condition = new CartContainsProducts($this->makeFacade());

        $this->expectException(InvalidConditionValueException::class);

        $condition->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::IN],
            [CartContainsProducts::PRODUCTS_LIST => []],
        );
    }
}
