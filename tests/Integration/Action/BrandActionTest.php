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

use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandToggleVisibilityEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\BrandQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class BrandActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsBrandWithDefaultPosition(): void
    {
        $event = new BrandCreateEvent();
        $event->setTitle('Nike')->setLocale('en_US')->setVisible(true);

        $this->dispatch($event, TheliaEvents::BRAND_CREATE);

        $brand = $event->getBrand();
        self::assertNotNull($brand);
        self::assertSame(1, $brand->getVisible());
        self::assertGreaterThan(0, $brand->getPosition());
        self::assertSame('Nike', $brand->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesI18nFields(): void
    {
        $brand = $this->factory->brand(['title' => 'Original']);

        $event = new BrandUpdateEvent($brand->getId());
        $event
            ->setTitle('Updated')
            ->setLocale('en_US')
            ->setVisible(true)
            ->setChapo('A short chapo')
            ->setDescription('A description')
            ->setPostscriptum('PS')
            ->setLogoImageId(null);

        $this->dispatch($event, TheliaEvents::BRAND_UPDATE);

        $reloaded = BrandQuery::create()->findPk($brand->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Updated', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('A short chapo', $reloaded->getChapo());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $brand = $this->factory->brand(['visible' => 1]);

        $this->dispatch(new BrandToggleVisibilityEvent($brand), TheliaEvents::BRAND_TOGGLE_VISIBILITY);

        self::assertSame(0, (int) BrandQuery::create()->findPk($brand->getId())->getVisible());
    }

    public function testUpdatePositionMovesBrandToGivenAbsolutePosition(): void
    {
        $first = $this->factory->brand();
        $second = $this->factory->brand();
        $third = $this->factory->brand();

        $event = new UpdatePositionEvent($first->getId(), UpdatePositionEvent::POSITION_ABSOLUTE, 3);

        $this->dispatch($event, TheliaEvents::BRAND_UPDATE_POSITION);

        self::assertSame(3, BrandQuery::create()->findPk($first->getId())->getPosition());
    }

    public function testDeleteRemovesBrandFromDatabase(): void
    {
        $brand = $this->factory->brand();
        $brandId = $brand->getId();

        $this->dispatch(new BrandDeleteEvent($brandId), TheliaEvents::BRAND_DELETE);

        self::assertNull(BrandQuery::create()->findPk($brandId));
    }
}
