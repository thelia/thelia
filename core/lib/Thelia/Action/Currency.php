<?php

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
    /** @var CurrencyConverter */
    protected $currencyConverter;

    public function __construct(CurrencyConverter $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Create a new currencyuration entry.
     *
     * @param $eventName
     */
    public function create(CurrencyCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $currency = new CurrencyModel();

        $isDefault = CurrencyQuery::create()->count() === 0;

        $currency

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
     * Change a currency.
     *
     * @param $eventName
     */
    public function update(CurrencyUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $currency = CurrencyQuery::create()->findPk($event->getCurrencyId())) {
            $currency

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
     * Set the default currency.
     *
     * @param $eventName
     */
    public function setDefault(CurrencyUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $currency = CurrencyQuery::create()->findPk($event->getCurrencyId())) {
            // Reset default status
            CurrencyQuery::create()->filterByByDefault(true)->update(['ByDefault' => false]);

            $currency

                ->setVisible($event->getVisible())
                ->setByDefault($event->getIsDefault())
                ->save()
            ;

            // Update rates when setting a new default currency
            if ($event->getIsDefault()) {
                $updateRateEvent = new CurrencyUpdateRateEvent();

                $dispatcher->dispatch($updateRateEvent, TheliaEvents::CURRENCY_UPDATE_RATES);
            }

            $event->setCurrency($currency);
        }
    }

    public function setVisible(CurrencyUpdateEvent $event)
    {
        if (null !== $currency = CurrencyQuery::create()->findPk($event->getCurrencyId())) {
            if (!$currency->getByDefault()) {
                $currency->setVisible($event->getVisible())->save();
            }
        }
    }

    /**
     * Delete a currencyuration entry.
     *
     * @param $eventName
     */
    public function delete(CurrencyDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($currency = CurrencyQuery::create()->findPk($event->getCurrencyId()))) {
            if ($currency->getByDefault()) {
                throw new \RuntimeException(
                    Translator::getInstance()->trans('It is not allowed to delete the default currency')
                );
            }

            $currency

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
                    sprintf('Unable to find exchange rate for currency %s, ID %d', $currency->getCode(), $currency->getId())
                );
                $event->addUndefinedRate($currency->getId());
            }
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param string $eventName
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(CurrencyQuery::create(), $event, $dispatcher);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CURRENCY_CREATE => ['create', 128],
            TheliaEvents::CURRENCY_UPDATE => ['update', 128],
            TheliaEvents::CURRENCY_DELETE => ['delete', 128],
            TheliaEvents::CURRENCY_SET_DEFAULT => ['setDefault', 128],
            TheliaEvents::CURRENCY_SET_VISIBLE => ['setVisible', 128],
            TheliaEvents::CURRENCY_UPDATE_RATES => ['updateRates', 128],
            TheliaEvents::CURRENCY_UPDATE_POSITION => ['updatePosition', 128],
        ];
    }
}
