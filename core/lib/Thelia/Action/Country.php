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

namespace Thelia\Action;

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
 * Class Country
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Country extends BaseAction implements EventSubscriberInterface
{
    public function create(CountryCreateEvent $event)
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

    public function update(CountryUpdateEvent $event)
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

    public function delete(CountryDeleteEvent $event)
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->delete();

            $event->setCountry($country);
        }
    }

    public function toggleDefault(CountryToggleDefaultEvent $event)
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->toggleDefault();

            $event->setCountry($country);
        }
    }

    /**
     * Toggle Country visibility
     *
     * @param CountryToggleVisibilityEvent $event
     */
    public function toggleVisibility(CountryToggleVisibilityEvent $event)
    {
        $country = $event->getCountry();

        $country
            ->setDispatcher($event->getDispatcher())
            ->setVisible(!$country->getVisible())
            ->save();

        $event->setCountry($country);
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
            TheliaEvents::COUNTRY_TOGGLE_DEFAULT    => array('toggleDefault', 128),
            TheliaEvents::COUNTRY_TOGGLE_VISIBILITY => array('toggleVisibility', 128)
        );
    }
}
