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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Currency as CurrencyModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Event\UpdatePositionEvent;

class Currency extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new currencyuration entry
     *
     * @param \Thelia\Core\Event\Currency\CurrencyCreateEvent $event
     */
    public function create(CurrencyCreateEvent $event)
    {
        $currency = new CurrencyModel();

        $currency
            ->setDispatcher($event->getDispatcher())

            ->setLocale($event->getLocale())
            ->setName($event->getCurrencyName())
            ->setSymbol($event->getSymbol())
            ->setRate($event->getRate())
            ->setCode(strtoupper($event->getCode()))

            ->save()
        ;

        $event->setCurrency($currency);
    }

    /**
     * Change a currency
     *
     * @param \Thelia\Core\Event\Currency\CurrencyUpdateEvent $event
     */
    public function update(CurrencyUpdateEvent $event)
    {
        $search = CurrencyQuery::create();

        if (null !== $currency = CurrencyQuery::create()->findPk($event->getCurrencyId())) {

            $currency
                ->setDispatcher($event->getDispatcher())

                ->setLocale($event->getLocale())
                ->setName($event->getCurrencyName())
                ->setSymbol($event->getSymbol())
                ->setRate($event->getRate())
                ->setCode(strtoupper($event->getCode()))

                ->save();

            $event->setCurrency($currency);
        }
    }

    /**
     * Set the default currency
     *
     * @param CurrencyUpdateEvent $event
     */
    public function setDefault(CurrencyUpdateEvent $event)
    {

        if (null !== $currency = CurrencyQuery::create()->findPk($event->getCurrencyId())) {
            // Reset default status
            CurrencyQuery::create()->filterByByDefault(true)->update(array('ByDefault' => false));

            $currency
                ->setDispatcher($event->getDispatcher())
                ->setByDefault($event->getIsDefault())
                ->save()
            ;

            $event->setCurrency($currency);
        }
    }

    /**
     * Delete a currencyuration entry
     *
     * @param \Thelia\Core\Event\Currency\CurrencyDeleteEvent $event
     */
    public function delete(CurrencyDeleteEvent $event)
    {

        if (null !== ($currency = CurrencyQuery::create()->findPk($event->getCurrencyId()))) {

            $currency
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setCurrency($currency);
        }
    }

    public function updateRates(ActionEvent $event)
    {
        $rates_url = ConfigQuery::read('currency_rate_update_url', 'http://www.ecb.int/stats/eurofxref/eurofxref-daily.xml');

        $rate_data = @file_get_contents($rates_url);

        if ($rate_data && $sxe = new \SimpleXMLElement($rate_data)) {

            foreach ($sxe->Cube[0]->Cube[0]->Cube as $last) {
                $code = strtoupper($last["currency"]);
                $rate = floatval($last['rate']);

                if (null !== $currency = CurrencyQuery::create()->findOneByCode($code)) {
                    $currency
                        ->setDispatcher($event->getDispatcher())
                        ->setRate($rate)
                        ->save()
                    ;
                }
            }
        } else {
            throw new \RuntimeException(sprintf("Failed to get currency rates data from URL %s", $rates_url));
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(CurrencyQuery::create(), $event);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CURRENCY_CREATE          => array("create", 128),
            TheliaEvents::CURRENCY_UPDATE          => array("update", 128),
            TheliaEvents::CURRENCY_DELETE          => array("delete", 128),
            TheliaEvents::CURRENCY_SET_DEFAULT     => array("setDefault", 128),
            TheliaEvents::CURRENCY_UPDATE_RATES    => array("updateRates", 128),
            TheliaEvents::CURRENCY_UPDATE_POSITION => array("updatePosition", 128)
        );
    }
}
