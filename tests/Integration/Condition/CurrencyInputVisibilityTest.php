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
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The currency select of a coupon condition offers the currencies the shop uses,
 * not every row of the currency table.
 */
final class CurrencyInputVisibilityTest extends IntegrationTestCase
{
    public function testTheCurrencySelectListsOnlyVisibleCurrencies(): void
    {
        $this->createFixtureFactory()->currency();

        $hidden = (new Currency())
            ->setCode('ZZZ')
            ->setSymbol('zz')
            ->setRate(1.0)
            ->setVisible(0)
            ->setByDefault(0);
        $hidden->save();

        $currencies = $this->renderedCurrencies();

        self::assertNotSame([], $currencies);
        self::assertArrayNotHasKey('ZZZ', $currencies, 'a hidden currency must not be offered');
        self::assertCount(CurrencyQuery::create()->filterByVisible(true)->count(), $currencies);
    }

    /**
     * @return array<string, string>
     */
    private function renderedCurrencies(): array
    {
        $rendered = [];

        $parser = $this->createMock(ParserInterface::class);
        $parser->method('render')->willReturnCallback(
            static function (string $template, array $parameters = []) use (&$rendered): string {
                if (isset($parameters['currencies'])) {
                    $rendered = $parameters['currencies'];
                }

                return '';
            },
        );

        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn(Translator::getInstance());
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getParser')->willReturn($parser);

        (new MatchForTotalAmount($facade))->drawBackOfficeInputs();

        return $rendered;
    }
}
