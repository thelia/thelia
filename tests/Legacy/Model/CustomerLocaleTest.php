<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Model;

use PHPUnit\Framework\TestCase;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * The customer.lang_id column is nullable, and the lang foreign key sets it back
 * to NULL when a language is deleted, so a customer without a language is a
 * reachable state that getLocale() has to answer for.
 */
class CustomerLocaleTest extends TestCase
{
    public function testLocaleFallsBackToTheDefaultLanguageWhenTheCustomerHasNone(): void
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(1);
        $this->assertNotNull($defaultLang, 'the database defines a default language');

        $customer = new Customer();
        $this->assertNull($customer->getLangId(), 'a new customer carries no language');

        $this->assertEquals($defaultLang->getLocale(), $customer->getLocale());
    }

    public function testLocaleIsTheCustomerOwnLanguageWhenItHasOne(): void
    {
        $lang = new Lang();
        $lang->setLocale('de_DE');

        $customer = new Customer();
        $customer->setLangModel($lang);

        $this->assertEquals('de_DE', $customer->getLocale());
    }
}
