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

namespace Thelia\Tests\Unit\Domain\Catalog\Currency;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Currency\CurrencyFacade;
use Thelia\Domain\Catalog\Currency\DTO\CurrencyCreateDTO;
use Thelia\Domain\Catalog\Currency\DTO\CurrencyUpdateDTO;
use Thelia\Model\Currency;

class CurrencyFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private CurrencyFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new CurrencyFacade($this->dispatcher);
    }

    public function testCreate(): void
    {
        $dto = new CurrencyCreateDTO(
            name: 'US Dollar',
            code: 'USD',
            symbol: '$',
            locale: 'en_US',
            rate: 1.1,
            format: '%n %s',
        );

        $currency = $this->createCurrencyMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (CurrencyCreateEvent $event) use ($currency) {
                    self::assertSame('US Dollar', $event->getCurrencyName());
                    self::assertSame('USD', $event->getCode());
                    self::assertSame('$', $event->getSymbol());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertSame(1.1, $event->getRate());
                    self::assertSame('%n %s', $event->getFormat());

                    $event->setCurrency($currency);

                    return true;
                }),
                TheliaEvents::CURRENCY_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($currency, $result);
    }

    public function testCreateMinimal(): void
    {
        $dto = new CurrencyCreateDTO(
            name: 'Euro',
            code: 'EUR',
            symbol: '€',
            locale: 'fr_FR',
        );

        $currency = $this->createCurrencyMock(11);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (CurrencyCreateEvent $event) use ($currency) {
                    self::assertSame('Euro', $event->getCurrencyName());
                    self::assertSame('EUR', $event->getCode());
                    self::assertSame('€', $event->getSymbol());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertSame(1.0, $event->getRate());
                    self::assertNull($event->getFormat());

                    $event->setCurrency($currency);

                    return true;
                }),
                TheliaEvents::CURRENCY_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($currency, $result);
    }

    public function testUpdate(): void
    {
        $dto = new CurrencyUpdateDTO(
            name: 'Updated Dollar',
            code: 'USD',
            symbol: '$',
            locale: 'en_US',
            rate: 1.2,
            format: '%s%n',
            visible: true,
            isDefault: false,
        );

        $currency = $this->createCurrencyMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (CurrencyUpdateEvent $event) use ($currency) {
                    self::assertSame(10, $event->getCurrencyId());
                    self::assertSame('Updated Dollar', $event->getCurrencyName());
                    self::assertSame('USD', $event->getCode());
                    self::assertSame('$', $event->getSymbol());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertSame(1.2, $event->getRate());
                    self::assertSame('%s%n', $event->getFormat());
                    self::assertSame(1, $event->getVisible());
                    self::assertSame(0, $event->getIsDefault());

                    $event->setCurrency($currency);

                    return true;
                }),
                TheliaEvents::CURRENCY_UPDATE
            );

        $result = $this->facade->update(10, $dto);

        $this->assertSame($currency, $result);
    }

    public function testCurrencyCreateDTOToArray(): void
    {
        $dto = new CurrencyCreateDTO(
            name: 'US Dollar',
            code: 'USD',
            symbol: '$',
            locale: 'en_US',
            rate: 1.1,
            format: '%n %s',
        );

        $array = $dto->toArray();

        $this->assertSame('US Dollar', $array['name']);
        $this->assertSame('USD', $array['code']);
        $this->assertSame('$', $array['symbol']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(1.1, $array['rate']);
        $this->assertSame('%n %s', $array['format']);
    }

    public function testCurrencyCreateDTODefaultValues(): void
    {
        $dto = new CurrencyCreateDTO(
            name: 'Euro',
            code: 'EUR',
            symbol: '€',
            locale: 'fr_FR',
        );

        $this->assertSame(1.0, $dto->rate);
        $this->assertNull($dto->format);
    }

    public function testCurrencyUpdateDTOToArray(): void
    {
        $dto = new CurrencyUpdateDTO(
            name: 'Updated Dollar',
            code: 'USD',
            symbol: '$',
            locale: 'en_US',
            rate: 1.2,
            format: '%s%n',
            visible: true,
            isDefault: true,
        );

        $array = $dto->toArray();

        $this->assertSame('Updated Dollar', $array['name']);
        $this->assertSame('USD', $array['code']);
        $this->assertSame('$', $array['symbol']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(1.2, $array['rate']);
        $this->assertSame('%s%n', $array['format']);
        $this->assertTrue($array['visible']);
        $this->assertTrue($array['is_default']);
    }

    public function testCurrencyUpdateDTODefaultValues(): void
    {
        $dto = new CurrencyUpdateDTO(
            name: 'Euro',
            code: 'EUR',
            symbol: '€',
            locale: 'fr_FR',
        );

        $this->assertSame(1.0, $dto->rate);
        $this->assertNull($dto->format);
        $this->assertTrue($dto->visible);
        $this->assertFalse($dto->isDefault);
    }

    private function createCurrencyMock(int $id): MockObject&Currency
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('getId')->willReturn($id);

        return $currency;
    }
}
