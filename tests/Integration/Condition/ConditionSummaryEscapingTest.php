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
use Thelia\Condition\Implementation\CartContainsCategories;
use Thelia\Condition\Implementation\CartContainsProducts;
use Thelia\Condition\Implementation\ForSomeCustomers;
use Thelia\Condition\Implementation\MatchDeliveryCountries;
use Thelia\Condition\Operators;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

/**
 * A condition summary is rendered as HTML in the back office: the message
 * carries its own markup on purpose, so every value read from the database and
 * inserted into it must arrive inert.
 *
 * The real Translator is needed here: it is what interpolates the parameters
 * into the message, and it does so without escaping them.
 */
final class ConditionSummaryEscapingTest extends IntegrationTestCase
{
    private const PAYLOAD = '<svg onload=1>';

    public function testACustomerNameIsInertInTheSummary(): void
    {
        $fixtures = $this->createFixtureFactory();

        $customer = $fixtures->customer(
            $fixtures->customerTitle(),
            ['firstname' => self::PAYLOAD, 'lastname' => 'Doe'],
        );

        $condition = (new ForSomeCustomers($this->makeFacade()))->setValidatorsFromForm(
            [ForSomeCustomers::CUSTOMERS_LIST => Operators::IN],
            [ForSomeCustomers::CUSTOMERS_LIST => [$customer->getId()]],
        );

        $this->assertSummaryIsInert($condition->getSummary());
    }

    public function testAProductTitleIsInertInTheSummary(): void
    {
        $fixtures = $this->createFixtureFactory();

        $product = $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => self::PAYLOAD, 'locale' => $this->currentLocale()],
        );

        $condition = (new CartContainsProducts($this->makeFacade()))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::IN],
            [CartContainsProducts::PRODUCTS_LIST => [$product->getId()]],
        );

        $this->assertSummaryIsInert($condition->getSummary());
    }

    public function testACategoryTitleIsInertInTheSummary(): void
    {
        $fixtures = $this->createFixtureFactory();

        $category = $fixtures->category();
        $category
            ->setLocale($this->currentLocale())
            ->setTitle(self::PAYLOAD)
            ->save($this->getPropelConnection());

        $condition = (new CartContainsCategories($this->makeFacade()))->setValidatorsFromForm(
            [CartContainsCategories::CATEGORIES_LIST => Operators::IN],
            [CartContainsCategories::CATEGORIES_LIST => [$category->getId()]],
        );

        $this->assertSummaryIsInert($condition->getSummary());
    }

    public function testACountryTitleIsInertInTheSummary(): void
    {
        $fixtures = $this->createFixtureFactory();

        $country = $fixtures->country(['isocode' => '999', 'isoalpha2' => 'ZZ', 'isoalpha3' => 'ZZZ']);
        $country
            ->setLocale($this->currentLocale())
            ->setTitle(self::PAYLOAD)
            ->save($this->getPropelConnection());

        $condition = (new MatchDeliveryCountries($this->makeFacade()))->setValidatorsFromForm(
            [MatchDeliveryCountries::COUNTRIES_LIST => Operators::IN],
            [MatchDeliveryCountries::COUNTRIES_LIST => [$country->getId()]],
        );

        $this->assertSummaryIsInert($condition->getSummary());
    }

    private function assertSummaryIsInert(string $summary): void
    {
        self::assertStringContainsString('&lt;svg', $summary);
        self::assertStringNotContainsString('<svg', $summary);
    }

    private function currentLocale(): string
    {
        return Lang::getDefaultLanguage()->getLocale();
    }

    private function makeFacade(): FacadeInterface
    {
        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn(Translator::getInstance());
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getRequest')->willReturn(
            static::getContainer()->get('request_stack')->getCurrentRequest(),
        );

        return $facade;
    }
}
