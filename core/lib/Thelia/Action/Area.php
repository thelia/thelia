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
            if (\count($country) === 1) {
                $country[1] = null;
            }

            if ($country[1] == 0) {
                $country[1] = null;
            }

            $countryArea
                ->setAreaId($area->getId())
                ->setCountryId($country[0])
                ->setStateId($country[1])
                ->save()
            ;
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

        return $area;
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
            TheliaEvents::AREA_POSTAGE_UPDATE => ['updatePostage', 128],
            TheliaEvents::AREA_DELETE => ['delete', 128],
            TheliaEvents::AREA_CREATE => ['save', 128],
            TheliaEvents::AREA_UPDATE => ['save', 128],
        ];
    }
}
