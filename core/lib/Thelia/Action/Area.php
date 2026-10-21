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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Event\AreaEvent;
use Thelia\Model\Map\CountryAreaTableMap;

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

        // The country leaves with its rules or not at all: a zone must not lose a country and
        // keep the rules written for it.
        $connection = Propel::getWriteConnection(CountryAreaTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            CountryAreaQuery::create()
                ->filterByCountryId($event->getCountryIds())
                ->filterByStateId($event->getStateId())
                ->filterByAreaId($area->getId())
                ->delete($connection);

            $deletedPatterns = $this->removeZipCodeRulesOfCountriesThatLeft((int) $area->getId(), $event->getCountryIds(), $connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        // Written once the deletion is committed. The merchant removed a country, not its
        // rules: the log keeps what went with it.
        foreach ($deletedPatterns as $countryId => $patterns) {
            Tlog::getInstance()->notice(
                'Country {country_id} left shipping zone {area_id}: {count} postal code rule(s) deleted with it ({patterns}).',
                ['country_id' => $countryId, 'area_id' => $area->getId(), 'count' => \count($patterns), 'patterns' => implode(', ', $patterns)],
            );
        }

        return $area;
    }

    /**
     * A postal code rule written for a country applies to no one once the country left the
     * zone, and would apply again, unseen, if the country came back: it goes with it. A
     * country still in the zone through another of its states keeps its rules.
     *
     * @param array<int|string> $countryIds
     *
     * @return array<int, list<string>> the patterns of the rules deleted, by country
     */
    private function removeZipCodeRulesOfCountriesThatLeft(int $areaId, array $countryIds, ConnectionInterface $connection): array
    {
        $deletedPatterns = [];

        foreach (array_unique(array_map('intval', $countryIds)) as $countryId) {
            if (CountryAreaQuery::create()->filterByAreaId($areaId)->filterByCountryId($countryId)->exists($connection)) {
                continue;
            }

            $rules = AreaZipCodeRuleQuery::create()
                ->filterByAreaId($areaId)
                ->filterByCountryId($countryId)
                ->find($connection);

            foreach ($rules as $rule) {
                $deletedPatterns[$countryId][] = ($rule->getExcluded() ? 'excluded ' : 'included ').$rule->getPattern();
            }

            // One by one, so that the model hooks of each deleted rule run.
            $rules->delete($connection);
        }

        return $deletedPatterns;
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
