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

use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleVisibilityEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CountryQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class CountryActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsCountryWithIsoCodesAndI18n(): void
    {
        $event = new CountryCreateEvent();
        $event
            ->setIsocode('999')
            ->setIsoAlpha2('TE')
            ->setIsoAlpha3('TES')
            ->setLocale('en_US')
            ->setTitle('Test Country')
            ->setVisible(true)
            ->setHasStates(false);

        $this->dispatch($event, TheliaEvents::COUNTRY_CREATE);

        $country = $event->getCountry();
        self::assertNotNull($country);
        self::assertSame('TE', $country->getIsoalpha2());
        self::assertSame('Test Country', $country->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesI18nAndZipCodeFormat(): void
    {
        $country = $this->dispatch(
            (new CountryCreateEvent())
                ->setIsocode('998')
                ->setIsoAlpha2('UP')
                ->setIsoAlpha3('UPD')
                ->setLocale('en_US')
                ->setTitle('Old Name')
                ->setVisible(true)
                ->setHasStates(false),
            TheliaEvents::COUNTRY_CREATE,
        )->getCountry();

        $event = new CountryUpdateEvent($country->getId());
        $event
            ->setIsocode('998')
            ->setIsoAlpha2('UP')
            ->setIsoAlpha3('UPD')
            ->setLocale('en_US')
            ->setTitle('New Name')
            ->setVisible(true)
            ->setHasStates(false)
            ->setNeedZipCode(true)
            ->setZipCodeFormat('#####')
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::COUNTRY_UPDATE);

        $reloaded = CountryQuery::create()->findPk($country->getId());
        self::assertSame('New Name', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame(1, (int) $reloaded->getNeedZipCode());
        self::assertSame('#####', $reloaded->getZipCodeFormat());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $country = $this->dispatch(
            (new CountryCreateEvent())
                ->setIsocode('997')
                ->setIsoAlpha2('TG')
                ->setIsoAlpha3('TGV')
                ->setLocale('en_US')
                ->setTitle('Toggle')
                ->setVisible(true)
                ->setHasStates(false),
            TheliaEvents::COUNTRY_CREATE,
        )->getCountry();

        $event = new CountryToggleVisibilityEvent();
        $event->setCountry($country);
        $this->dispatch($event, TheliaEvents::COUNTRY_TOGGLE_VISIBILITY);

        self::assertSame(0, (int) CountryQuery::create()->findPk($country->getId())->getVisible());
    }

    public function testDeleteRemovesCountry(): void
    {
        $country = $this->dispatch(
            (new CountryCreateEvent())
                ->setIsocode('996')
                ->setIsoAlpha2('DE')
                ->setIsoAlpha3('DEL')
                ->setLocale('en_US')
                ->setTitle('Disposable')
                ->setVisible(true)
                ->setHasStates(false),
            TheliaEvents::COUNTRY_CREATE,
        )->getCountry();
        $countryId = $country->getId();

        $this->dispatch(new CountryDeleteEvent($countryId), TheliaEvents::COUNTRY_DELETE);

        self::assertNull(CountryQuery::create()->findPk($countryId));
    }
}
