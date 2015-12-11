<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace TheliaMigrateCountry\EventListeners;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Model\AddressQuery;
use Thelia\Model\Base\CountryQuery;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CountryAreaTableMap;
use Thelia\Model\Map\TaxRuleCountryTableMap;
use Thelia\Model\TaxRuleCountryQuery;
use TheliaMigrateCountry\Events\MigrateCountryEvent;
use TheliaMigrateCountry\Events\MigrateCountryEvents;

/**
 * Class MigrateCountryListener
 * @package TheliaMigrateCountry\EventListeners
 * @author Julien Chanséaume <julien@thelia.net>
 */
class MigrateCountryListener implements EventSubscriberInterface
{

    public function migrateCountry(MigrateCountryEvent $event)
    {
        $counter = [];

        // update address
        $counter[AddressTableMap::TABLE_NAME] = $this->migrateAddress($event);

        // tax rules
        $counter[TaxRuleCountryTableMap::TABLE_NAME] = $this->migrateAddress($event);

        // shipping zone
        $counter[CountryAreaTableMap::TABLE_NAME] = $this->migrateAddress($event);

        // if it succeeds we toggle the visibility of old country and new
        $this->setCountriesVisibility($event);

        $event->setCounter($counter);

    }

    protected function migrateAddress(MigrateCountryEvent $event)
    {
        $con = Propel::getWriteConnection(AddressTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $updatedRows = AddressQuery::create()
                ->filterByCountryId($event->getCountry())
                ->update(
                    [
                        'CountryId' => $event->getNewCountry(),
                        'StateId' => $event->getNewState(),
                    ]
                );

            $con->commit();

            return $updatedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    protected function migrateTaxRules(MigrateCountryEvent $event)
    {
        $con = Propel::getWriteConnection(TaxRuleCountryTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $updatedRows = TaxRuleCountryQuery::create()
                ->filterByCountryId($event->getCountry())
                ->update(
                    [
                        'CountryId' => $event->getNewCountry(),
                        'StateId' => $event->getNewState(),
                    ]
                );

            $con->commit();

            return $updatedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    protected function migrateShippingZones(MigrateCountryEvent $event)
    {
        $con = Propel::getWriteConnection(CountryAreaTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $updatedRows = CountryAreaQuery::create()
                ->filterByCountryId($event->getCountry())
                ->update(
                    [
                        'CountryId' => $event->getNewCountry(),
                        'StateId' => $event->getNewState(),
                    ]
                );

            $con->commit();

            return $updatedRows;
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    private function setCountriesVisibility(MigrateCountryEvent $event)
    {
        $oldCountry = CountryQuery::create()->findPk($event->getCountry());

        if (null !== $oldCountry) {
            $oldCountry
                ->setVisible(0)
                ->save()
            ;
        }

        $newCountry = CountryQuery::create()->findPk($event->getNewCountry());
        if (null !== $newCountry) {
            $newCountry
                ->setVisible(1)
                ->save()
            ;
        }
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            MigrateCountryEvents::MIGRATE_COUNTRY => 'migrateCountry'
        ];
    }
}
