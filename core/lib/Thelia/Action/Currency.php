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
