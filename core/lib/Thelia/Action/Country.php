<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleDefaultEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Country as CountryModel;
use Thelia\Model\CountryQuery;

/**
 * Class Country
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Country extends BaseAction implements EventSubscriberInterface
{

    public function create(CountryCreateEvent $event)
    {
        $country = new CountryModel();

        $country
            ->setIsocode($event->getIsocode())
            ->setIsoalpha2($event->getIsoAlpha2())
            ->setIsoalpha3($event->getIsoAlpha3())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save();

        $event->setCountry($country);

    }

    public function update(CountryUpdateEvent $event)
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country
                ->setIsocode($event->getIsocode())
                ->setIsoalpha2($event->getIsoAlpha2())
                ->setIsoalpha3($event->getIsoAlpha3())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setChapo($event->getChapo())
                ->setDescription($event->getDescription())
                ->save();

            $event->setCountry($country);
        }
    }

    public function delete(CountryDeleteEvent $event)
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->delete();

            $event->setCountry($country);
        }
    }

    public function toggleDefault(CountryToggleDefaultEvent $event)
    {
        if ( null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->toggleDefault();

            $event->setCountry($country);
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::COUNTRY_CREATE            => array('create', 128),
            TheliaEvents::COUNTRY_UPDATE            => array('update', 128),
            TheliaEvents::COUNTRY_DELETE            => array('delete', 128),
            TheliaEvents::COUNTRY_TOGGLE_DEFAULT    => array('toggleDefault', 128)
        );
    }
}
