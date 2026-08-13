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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The customer.lang_id column is nullable, and the lang foreign key sets it back
 * to NULL when a language is deleted, so a customer without a language is a
 * reachable state that getLocale() has to answer for.
 */
final class CustomerLocaleTest extends IntegrationTestCase
{
    public function testLocaleFallsBackToTheDefaultLanguageWhenTheCustomerHasNone(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        self::assertNull($customer->getLangId(), 'the fixture leaves the customer without a language');

        $defaultLang = LangQuery::create()->findOneByByDefault(1);
        self::assertNotNull($defaultLang, 'the test database defines a default language');

        self::assertSame($defaultLang->getLocale(), $customer->getLocale());
    }

    public function testLocaleIsTheCustomerOwnLanguageWhenItHasOne(): void
    {
        $factory = $this->createFixtureFactory();
        $lang = $factory->lang(['title' => 'Deutsch', 'code' => 'de', 'locale' => 'de_DE']);
        $customer = $factory->customer($factory->customerTitle());
        $customer->setLangId((int) $lang->getId());
        $customer->save();

        self::assertSame('de_DE', $customer->getLocale());
    }
}
