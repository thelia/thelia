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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Model\Country;
use Thelia\Test\IntegrationTestCase;

/**
 * attributeCountry() reads the shop's default country. Its argument names the
 * attribute wanted, the way attributeCurrency() and attributeBrand() take one,
 * and not which country to read: there is only one country to read here.
 */
final class AttributeAccessCountryTest extends IntegrationTestCase
{
    private AttributeAccessService $attributeAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeAccess = static::getContainer()->get(AttributeAccessService::class);
    }

    public function testItReadsAnI18nAttributeOfTheDefaultCountry(): void
    {
        $defaultCountry = Country::getDefaultCountry();
        $defaultCountry->setLocale($this->currentLocale());

        self::assertSame(
            $defaultCountry->getTitle(),
            $this->attributeAccess->attributeCountry('title'),
        );
    }

    public function testItReadsAColumnOfTheDefaultCountry(): void
    {
        self::assertSame(
            Country::getDefaultCountry()->getIsoalpha2(),
            $this->attributeAccess->attributeCountry('isoalpha2'),
        );
    }

    /**
     * An attribute the country does not carry is a caller mistake, and the
     * service says so rather than answering an empty string that looks like a
     * country without a name.
     */
    public function testItRejectsAnAttributeTheCountryDoesNotCarry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("defaultCountry has no 'not_a_country_attribute' attribute");

        $this->attributeAccess->attributeCountry('not_a_country_attribute');
    }

    private function currentLocale(): string
    {
        $lang = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession()?->getLang();

        return $lang?->getLocale() ?? 'en_US';
    }
}
