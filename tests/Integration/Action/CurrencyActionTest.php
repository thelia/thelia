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

namespace Thelia\Tests\Integration\Action;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateRateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\CurrencyConverter\Provider\ECBProvider;
use Thelia\Model\CurrencyQuery;
use Thelia\Test\IntegrationTestCase;

final class CurrencyActionTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
    }

    public function testCreateCurrencyPersistsWithUppercaseCode(): void
    {
        $event = new CurrencyCreateEvent();
        $event
            ->setCurrencyName('Swiss Franc')
            ->setLocale('en_US')
            ->setSymbol('CHF')
            ->setFormat('#,###.##')
            ->setCode('chf')
            ->setIsocodeNumeric('756')
            ->setRate(0.92);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_CREATE);

        $currency = $event->getCurrency();
        self::assertNotNull($currency);
        self::assertSame('CHF', $currency->getCode(), 'Code must be uppercased by the listener');
        self::assertSame('756', $currency->getIsocodeNumeric());
        self::assertEqualsWithDelta(0.92, (float) $currency->getRate(), 0.001);
    }

    public function testUpdateCurrencyRate(): void
    {
        $createEvent = new CurrencyCreateEvent();
        $createEvent
            ->setCurrencyName('British Pound')
            ->setLocale('en_US')
            ->setSymbol('£')
            ->setCode('GBP')
            ->setRate(0.85);
        $this->dispatcher->dispatch($createEvent, TheliaEvents::CURRENCY_CREATE);

        $currencyId = $createEvent->getCurrency()->getId();

        $updateEvent = new CurrencyUpdateEvent($currencyId);
        $updateEvent
            ->setCurrencyName('British Pound Sterling')
            ->setLocale('en_US')
            ->setSymbol('£')
            ->setCode('GBP')
            ->setIsocodeNumeric('826')
            ->setRate(0.88);
        $this->dispatcher->dispatch($updateEvent, TheliaEvents::CURRENCY_UPDATE);

        $reloaded = CurrencyQuery::create()->findPk($currencyId);
        self::assertNotNull($reloaded);
        self::assertSame('British Pound Sterling', $reloaded->setLocale('en_US')->getName());
        self::assertSame('826', $reloaded->getIsocodeNumeric());
        self::assertEqualsWithDelta(0.88, (float) $reloaded->getRate(), 0.001);
    }

    public function testUpdateRatesReadsTheFixedExchangeRateFeed(): void
    {
        // The test container builds the ECB provider without its web service
        // (see TestOfflineCurrencyProviderPass): rates come from a fixed feed.
        $provider = $this->getService('currency.converter.ecbProvider');
        self::assertInstanceOf(ECBProvider::class, $provider);
        $provider->loadFromXml((string) file_get_contents(\dirname(__DIR__, 2).'/fixtures/currency/eurofxref-daily.xml'));

        CurrencyQuery::create()->deleteAll();
        $ids = [];
        foreach ([['EUR', 1.0], ['USD', 9.0], ['GBP', 9.0], ['XYZ', 9.0]] as [$code, $rate]) {
            $event = new CurrencyCreateEvent();
            $event->setCurrencyName($code)->setLocale('en_US')->setSymbol($code)->setCode($code)->setRate($rate);
            $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_CREATE);
            $ids[$code] = $event->getCurrency()->getId();
        }

        $rateEvent = new CurrencyUpdateRateEvent();
        $this->dispatcher->dispatch($rateEvent, TheliaEvents::CURRENCY_UPDATE_RATES);

        $rateOf = static fn (string $code): float => (float) CurrencyQuery::create()->findPk($ids[$code])->getRate();

        self::assertEqualsWithDelta(1.0, $rateOf('EUR'), 0.0001);
        self::assertEqualsWithDelta(1.25, $rateOf('USD'), 0.0001);
        self::assertEqualsWithDelta(0.8, $rateOf('GBP'), 0.0001);
        self::assertEqualsWithDelta(9.0, $rateOf('XYZ'), 0.0001, 'A currency missing from the feed keeps its rate');
        self::assertSame([$ids['XYZ']], $rateEvent->getUndefinedRates());
    }

    public function testFirstCurrencyBecomesDefault(): void
    {
        // Remove all currencies first to test the "first = default" behavior
        CurrencyQuery::create()->deleteAll();

        $event = new CurrencyCreateEvent();
        $event
            ->setCurrencyName('Test Default')
            ->setLocale('en_US')
            ->setSymbol('$')
            ->setCode('TST')
            ->setRate(1.0);
        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_CREATE);

        self::assertTrue(
            (bool) $event->getCurrency()->getByDefault(),
            'First currency created should be the default'
        );
    }
}
