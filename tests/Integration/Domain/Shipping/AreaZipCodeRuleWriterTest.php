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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Domain\Shipping\ZipCode\AreaZipCodeRuleWriter;
use Thelia\Model\Area;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\CountryArea;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class AreaZipCodeRuleWriterTest extends IntegrationTestCase
{
    private FixtureFactory $factory;
    private AreaZipCodeRuleWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->writer = $this->getService(AreaZipCodeRuleWriter::class);
    }

    public function testARuleIsSavedInItsNormalForm(): void
    {
        $area = $this->area();

        $rule = $this->writer->add($area->getId(), ' sw1a 1aa ', true, null);

        self::assertSame('SW1A1AA', $rule->getPattern());
        self::assertTrue($rule->getExcluded());
        self::assertSame(1, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count());
    }

    public function testAPatternThatMatchesNothingIsRefused(): void
    {
        $this->expectExceptionMessage('A postal code pattern needs at least one character other than *.');

        $this->writer->add($this->area()->getId(), '*', false, null);
    }

    public function testARuleCannotNameACountryTheZoneDoesNotHold(): void
    {
        $area = $this->area();
        $foreign = $this->factory->country(['isoalpha2' => 'XS', 'isoalpha3' => 'XSX', 'isocode' => '992']);

        $this->expectExceptionMessage('A rule can only name a country of its zone.');

        $this->writer->add($area->getId(), '20*', true, $foreign->getId());
    }

    public function testARuleCanNameACountryOfItsZone(): void
    {
        $area = $this->area();
        $country = $this->factory->country(['isoalpha2' => 'XT', 'isoalpha3' => 'XTX', 'isocode' => '993']);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());

        self::assertSame($country->getId(), $this->writer->add($area->getId(), '20*', true, $country->getId())->getCountryId());
    }

    public function testRemovingARuleOfAnotherZoneLeavesIt(): void
    {
        $rule = $this->writer->add($this->area()->getId(), '20*', true, null);

        self::assertFalse($this->writer->remove($this->area()->getId(), $rule->getId()));
        self::assertNotNull(AreaZipCodeRuleQuery::create()->findPk($rule->getId()));
        self::assertTrue($this->writer->remove($rule->getAreaId(), $rule->getId()));
        self::assertNull(AreaZipCodeRuleQuery::create()->findPk($rule->getId()));
    }

    private function area(): Area
    {
        $area = (new Area())->setName('Writer zone');
        $area->save($this->getPropelConnection());

        return $area;
    }
}
