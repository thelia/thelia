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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleDefaultEvent;
use Thelia\Core\Event\Country\CountryToggleVisibilityEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Country as CountryModel;
use Thelia\Model\CountryQuery;

/**
 * Class Country.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Country extends BaseAction implements EventSubscriberInterface
{
    public function create(CountryCreateEvent $event): void
    {
        $country = new CountryModel();

        $country
            ->setVisible($event->isVisible())
            ->setIsocode($event->getIsocode())
            ->setIsoalpha2($event->getIsoAlpha2())
            ->setIsoalpha3($event->getIsoAlpha3())
            ->setHasStates($event->isHasStates())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save();

        $event->setCountry($country);
    }

    public function update(CountryUpdateEvent $event): void
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country
                ->setVisible($event->isVisible())
                ->setIsocode($event->getIsocode())
                ->setIsoalpha2($event->getIsoAlpha2())
                ->setIsoalpha3($event->getIsoAlpha3())
                ->setHasStates($event->isHasStates())
                ->setNeedZipCode($event->isNeedZipCode())
                ->setZipCodeFormat($event->getZipCodeFormat())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setChapo($event->getChapo())
                ->setDescription($event->getDescription())
                ->save();

            $event->setCountry($country);
        }
    }

    public function delete(CountryDeleteEvent $event): void
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->delete();

            $event->setCountry($country);
        }
    }

    public function toggleDefault(CountryToggleDefaultEvent $event): void
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->toggleDefault();

            $event->setCountry($country);
        }
    }

    /**
     * Toggle Country visibility.
     */
    public function toggleVisibility(CountryToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $country = $event->getCountry();

        $country

            ->setVisible(!$country->getVisible())
            ->save();

        $event->setCountry($country);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::COUNTRY_CREATE => ['create', 128],
            TheliaEvents::COUNTRY_UPDATE => ['update', 128],
            TheliaEvents::COUNTRY_DELETE => ['delete', 128],
            TheliaEvents::COUNTRY_TOGGLE_DEFAULT => ['toggleDefault', 128],
            TheliaEvents::COUNTRY_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
        ];
    }
}
