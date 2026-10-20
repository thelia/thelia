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
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Event\AreaEvent;

/**
 * Class Area.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Area extends BaseAction implements EventSubscriberInterface
{
    public function addCountry(AreaAddCountryEvent $event): void
    {
        $countryIds = $event->getCountryIds();

        $area = $event->getModel();

        foreach ($countryIds as $countryId) {
            $countryArea = new CountryArea();

            $country = explode('-', (string) $countryId);

            if (1 === \count($country)) {
                $country[1] = null;
            }

            if (0 === $country[1]) {
                $country[1] = null;
            }

            $countryArea
                ->setAreaId($area->getId())
                ->setCountryId((int) $country[0])
                ->setStateId(null !== $country[1] ? (int) $country[1] : null)
                ->save();
        }
    }

    public function removeCountry(AreaRemoveCountryEvent $event)
    {
        $area = $event->getModel();

        CountryAreaQuery::create()
            ->filterByCountryId($event->getCountryIds())
            ->filterByStateId($event->getStateId())
            ->filterByAreaId($area->getId())
            ->delete();

        $this->removeZipCodeRulesOfCountriesThatLeft((int) $area->getId(), $event->getCountryIds());

        return $area;
    }

    /**
     * A postal code rule written for a country applies to no one once the country left the
     * zone, and would apply again, unseen, if the country came back: it goes with it. A
     * country still in the zone through another of its states keeps its rules.
     *
     * @param array<int|string> $countryIds
     */
    private function removeZipCodeRulesOfCountriesThatLeft(int $areaId, array $countryIds): void
    {
        foreach (array_unique(array_map('intval', $countryIds)) as $countryId) {
            if (CountryAreaQuery::create()->filterByAreaId($areaId)->filterByCountryId($countryId)->exists()) {
                continue;
            }

            // One by one, so that the model hooks of each deleted rule run.
            AreaZipCodeRuleQuery::create()
                ->filterByAreaId($areaId)
                ->filterByCountryId($countryId)
                ->find()
                ->delete();
        }
    }

    public function delete(AreaEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $event->getModel()
            ->delete();
    }

    public function save(AreaEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $event->getModel()
            ->save();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::AREA_ADD_COUNTRY => ['addCountry', 128],
            TheliaEvents::AREA_REMOVE_COUNTRY => ['removeCountry', 128],
            TheliaEvents::AREA_DELETE => ['delete', 128],
            TheliaEvents::AREA_CREATE => ['save', 128],
            TheliaEvents::AREA_UPDATE => ['save', 128],
        ];
    }
}
