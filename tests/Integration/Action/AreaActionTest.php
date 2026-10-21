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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\Propel;
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Area;
use Thelia\Model\AreaQuery;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Event\AreaEvent;
use Thelia\Model\Map\CountryAreaTableMap;
use Thelia\Model\State;
use Thelia\Test\ActionIntegrationTestCase;

final class AreaActionTest extends ActionIntegrationTestCase
{
    public function testCreateAreaThroughGenericSaveEvent(): void
    {
        $area = (new Area())->setName('Test Area');

        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_CREATE);

        $reloaded = AreaQuery::create()->findPk($area->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Test Area', $reloaded->getName());
    }

    public function testAddCountryAttachesCountriesToArea(): void
    {
        $area = $this->persistArea('Europe');
        $country = $this->factory->country();

        $this->dispatch(
            new AreaAddCountryEvent($area, [(string) $country->getId()]),
            TheliaEvents::AREA_ADD_COUNTRY,
        );

        $linked = CountryAreaQuery::create()
            ->filterByAreaId($area->getId())
            ->filterByCountryId($country->getId())
            ->count();
        self::assertSame(1, $linked);
    }

    public function testRemoveCountryDetachesCountryFromArea(): void
    {
        $area = $this->persistArea('France');
        $country = $this->factory->country();

        $this->dispatch(
            new AreaAddCountryEvent($area, [(string) $country->getId()]),
            TheliaEvents::AREA_ADD_COUNTRY,
        );

        $this->dispatch(
            new AreaRemoveCountryEvent($area, [(int) $country->getId()]),
            TheliaEvents::AREA_REMOVE_COUNTRY,
        );

        $remaining = CountryAreaQuery::create()
            ->filterByAreaId($area->getId())
            ->filterByCountryId($country->getId())
            ->count();
        self::assertSame(0, $remaining);
    }

    public function testTheRulesOfACountryThatLeavesTheZoneGoWithIt(): void
    {
        $area = $this->persistArea('Postal codes');
        $leaving = $this->factory->country(['isoalpha2' => 'XA', 'isoalpha3' => 'XAX', 'isocode' => '981']);
        $staying = $this->factory->country(['isoalpha2' => 'XB', 'isoalpha3' => 'XBX', 'isocode' => '982']);
        $this->dispatch(new AreaAddCountryEvent($area, [(string) $leaving->getId(), (string) $staying->getId()]), TheliaEvents::AREA_ADD_COUNTRY);
        $ruleOfTheLeavingCountry = $this->zipCodeRule($area, $leaving->getId());
        $ruleOfTheStayingCountry = $this->zipCodeRule($area, $staying->getId());
        $ruleOfEveryCountry = $this->zipCodeRule($area, null);

        $this->dispatch(new AreaRemoveCountryEvent($area, [(int) $leaving->getId()]), TheliaEvents::AREA_REMOVE_COUNTRY);

        self::assertNull(AreaZipCodeRuleQuery::create()->findPk($ruleOfTheLeavingCountry), 'A rule of a country out of the zone would apply again if it came back.');
        self::assertNotNull(AreaZipCodeRuleQuery::create()->findPk($ruleOfTheStayingCountry));
        self::assertNotNull(AreaZipCodeRuleQuery::create()->findPk($ruleOfEveryCountry));
    }

    public function testACountryStillInTheZoneThroughAnotherStateKeepsItsRules(): void
    {
        $area = $this->persistArea('Postal codes by state');
        $country = $this->factory->country(['isoalpha2' => 'XC', 'isoalpha3' => 'XCX', 'isocode' => '983']);
        $state = (new State())->setCountryId($country->getId())->setIsocode('XC1')->setVisible(1);
        $state->save();
        $this->dispatch(new AreaAddCountryEvent($area, [(string) $country->getId(), $country->getId().'-'.$state->getId()]), TheliaEvents::AREA_ADD_COUNTRY);
        $rule = $this->zipCodeRule($area, $country->getId());

        $this->dispatch(new AreaRemoveCountryEvent($area, [(int) $country->getId()], $state->getId()), TheliaEvents::AREA_REMOVE_COUNTRY);

        self::assertSame(1, CountryAreaQuery::create()->filterByAreaId($area->getId())->filterByCountryId($country->getId())->count());
        self::assertNotNull(AreaZipCodeRuleQuery::create()->findPk($rule));
    }

    /**
     * The rules cannot be read (their table is masked for the connection, with no implicit
     * commit): the removal of the country is rolled back with them. The test runs inside a
     * transaction of its own, so Propel only marks it as not committable instead of rolling
     * the rows back at once: that mark is what proves the action rolled back its work.
     */
    public function testACountryDoesNotLeaveTheZoneWithoutItsRules(): void
    {
        $area = $this->persistArea('Postal codes, all or nothing');
        $country = $this->factory->country(['isoalpha2' => 'XD', 'isoalpha3' => 'XDX', 'isocode' => '985']);
        $this->dispatch(new AreaAddCountryEvent($area, [(string) $country->getId()]), TheliaEvents::AREA_ADD_COUNTRY);
        $connection = Propel::getWriteConnection(CountryAreaTableMap::DATABASE_NAME);
        self::assertTrue($connection->isCommitable());

        $connection->exec('CREATE TEMPORARY TABLE `area_zip_code_rule` (id INT)');

        try {
            $this->dispatch(new AreaRemoveCountryEvent($area, [(int) $country->getId()]), TheliaEvents::AREA_REMOVE_COUNTRY);
            self::fail('The rules could not be read: the removal must fail.');
        } catch (\Propel\Runtime\Exception\ExceptionInterface) {
        } finally {
            $connection->exec('DROP TEMPORARY TABLE `area_zip_code_rule`');
        }

        self::assertFalse($connection->isCommitable(), 'The removal of the country was not rolled back.');
    }

    public function testDeleteRemovesArea(): void
    {
        $area = $this->persistArea('To delete');
        $areaId = $area->getId();

        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_DELETE);

        self::assertNull(AreaQuery::create()->findPk($areaId));
    }

    private function zipCodeRule(Area $area, ?int $countryId): int
    {
        $rule = (new AreaZipCodeRule())->setAreaId($area->getId())->setCountryId($countryId)->setPattern('20*')->setExcluded(true);
        $rule->save();

        return (int) $rule->getId();
    }

    private function persistArea(string $name): Area
    {
        $area = (new Area())->setName($name);
        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_CREATE);

        return $area;
    }
}
