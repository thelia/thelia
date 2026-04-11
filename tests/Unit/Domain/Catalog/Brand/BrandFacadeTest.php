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

namespace Thelia\Tests\Unit\Domain\Catalog\Brand;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Domain\Catalog\Brand\BrandFacade;
use Thelia\Domain\Catalog\Brand\DTO\BrandCreateDTO;
use Thelia\Domain\Catalog\Brand\DTO\BrandUpdateDTO;
use Thelia\Model\Brand;

/**
 * Covers the dispatch-only branches of BrandFacade. The methods that
 * call `getById()` (toggleVisibility, updateSeo) reach BrandQuery and
 * are exercised by the integration test suite instead.
 */
final class BrandFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private BrandFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new BrandFacade($this->dispatcher);
    }

    public function testCreateDispatchesBrandCreateEventWithDtoFields(): void
    {
        $dto = new BrandCreateDTO(title: 'Nike', locale: 'en_US', visible: true);
        $brand = $this->createMock(Brand::class);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (BrandCreateEvent $event) use ($brand): bool {
                    self::assertSame('Nike', $event->getTitle());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertTrue($event->getVisible());

                    $event->setBrand($brand);

                    return true;
                }),
                TheliaEvents::BRAND_CREATE,
            )
            ->willReturnArgument(0);

        self::assertSame($brand, $this->facade->create($dto));
    }

    public function testUpdateDispatchesBrandUpdateEventWithFullDtoPayload(): void
    {
        $dto = new BrandUpdateDTO(
            title: 'Adidas',
            locale: 'fr_FR',
            visible: false,
            chapo: 'Chapo',
            description: 'Desc',
            postscriptum: 'PS',
            logoImageId: 42,
        );
        $brand = $this->createMock(Brand::class);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (BrandUpdateEvent $event) use ($brand): bool {
                    self::assertSame(7, $event->getBrandId());
                    self::assertSame('Adidas', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertFalse($event->getVisible());
                    self::assertSame('Chapo', $event->getChapo());
                    self::assertSame('Desc', $event->getDescription());
                    self::assertSame('PS', $event->getPostscriptum());
                    self::assertSame(42, $event->getLogoImageId());

                    $event->setBrand($brand);

                    return true;
                }),
                TheliaEvents::BRAND_UPDATE,
            )
            ->willReturnArgument(0);

        self::assertSame($brand, $this->facade->update(7, $dto));
    }

    public function testDeleteDispatchesBrandDeleteEvent(): void
    {
        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (BrandDeleteEvent $event): bool {
                    self::assertSame(11, $event->getBrandId());

                    return true;
                }),
                TheliaEvents::BRAND_DELETE,
            )
            ->willReturnArgument(0);

        $this->facade->delete(11);
    }

    public function testUpdatePositionDispatchesUpdatePositionEvent(): void
    {
        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (UpdatePositionEvent $event): bool {
                    self::assertSame(3, $event->getObjectId());
                    self::assertSame(2, $event->getPosition());
                    self::assertSame(UpdatePositionEvent::POSITION_ABSOLUTE, $event->getMode());

                    return true;
                }),
                TheliaEvents::BRAND_UPDATE_POSITION,
            )
            ->willReturnArgument(0);

        $this->facade->updatePosition(3, 2);
    }
}
