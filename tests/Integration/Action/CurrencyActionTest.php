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
use Thelia\Core\Event\TheliaEvents;
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
            ->setRate(0.92);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_CREATE);

        $currency = $event->getCurrency();
        self::assertNotNull($currency);
        self::assertSame('CHF', $currency->getCode(), 'Code must be uppercased by the listener');
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
            ->setRate(0.88);
        $this->dispatcher->dispatch($updateEvent, TheliaEvents::CURRENCY_UPDATE);

        $reloaded = CurrencyQuery::create()->findPk($currencyId);
        self::assertNotNull($reloaded);
        self::assertSame('British Pound Sterling', $reloaded->setLocale('en_US')->getName());
        self::assertEqualsWithDelta(0.88, (float) $reloaded->getRate(), 0.001);
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
