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
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateRateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\CurrencyConverter\CurrencyConverter;
use Thelia\CurrencyConverter\Exception\CurrencyNotFoundException;
use Thelia\Log\Tlog;
use Thelia\Math\Number;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CurrencyQuery;

class Currency extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var CurrencyConverter
     */
    protected $currencyConverter;

    /**
     * @param CurrencyConverter $currencyConverter
     */
    public function __construct(CurrencyConverter $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
    }
    /**
     * Create a new currencyuration entry
     *
     * @param \Thelia\Core\Event\Currency\CurrencyCreateEvent $event
     */
    public function create(CurrencyCreateEvent $event)
    {
        $currency = new CurrencyModel();

        $isDefault = CurrencyQuery::create()->count() === 0;

        $currency
            ->setDispatcher($event->getDispatcher())
            ->setLocale($event->getLocale())
            ->setName($event->getCurrencyName())
            ->setSymbol($event->getSymbol())
            ->setFormat($event->getFormat())
            ->setRate($event->getRate())
            ->setCode(strtoupper($event->getCode()))
            ->setByDefault($isDefault)
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
                ->setFormat($event->getFormat())
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
                ->setVisible($event->getVisible())
                ->setByDefault($event->getIsDefault())
                ->save()
            ;

            // Update rates when setting a new default currency
            if ($event->getIsDefault()) {
                $updateRateEvent = new CurrencyUpdateRateEvent();

                $event->getDispatcher()->dispatch(TheliaEvents::CURRENCY_UPDATE_RATES, $updateRateEvent);
            }

            $event->setCurrency($currency);
        }
    }

    /**
     * @param CurrencyUpdateEvent $event
     */
    public function setVisible(CurrencyUpdateEvent $event)
    {
        if (null !== $currency = CurrencyQuery::create()->findPk($event->getCurrencyId())) {
            if (!$currency->getByDefault()) {
                $currency->setVisible($event->getVisible())->save();
            }
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
            if ($currency->getByDefault()) {
                throw new \RuntimeException(
                    Translator::getInstance()->trans('It is not allowed to delete the default currency')
                );
            }

            $currency
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setCurrency($currency);
        }
    }

    public function updateRates(CurrencyUpdateRateEvent $event)
    {
        if (null === $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true)) {
            throw new \RuntimeException('Unable to find a default currency, please define a default currency.');
        }

        $defaultCurrency->setRate(1)->save();

        $currencies = CurrencyQuery::create()->filterByByDefault(false);
        $baseValue = new Number('1');

        /** @var \Thelia\Model\Currency $currency */
        foreach ($currencies as $currency) {
            try {
                $rate = $this->currencyConverter
                    ->from($defaultCurrency->getCode())
                    ->to($currency->getCode())
                    ->convert($baseValue);

                $currency->setRate($rate->getNumber(-1))->save();
            } catch (CurrencyNotFoundException $ex) {
                Tlog::getInstance()->addError(
                    sprintf("Unable to find exchange rate for currency %s, ID %d", $currency->getCode(), $currency->getId())
                );
                $event->addUndefinedRate($currency->getId());
            }
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
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
            TheliaEvents::CURRENCY_SET_VISIBLE     => array("setVisible", 128),
            TheliaEvents::CURRENCY_UPDATE_RATES    => array("updateRates", 128),
            TheliaEvents::CURRENCY_UPDATE_POSITION => array("updatePosition", 128)
        );
    }
}
