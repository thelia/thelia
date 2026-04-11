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

use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleDeleteEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\SaleQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class SaleActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsSaleWithI18nAndLabel(): void
    {
        $event = new SaleCreateEvent();
        $event
            ->setLocale('en_US')
            ->setTitle('Summer sale')
            ->setSaleLabel('SUMMER');

        $this->dispatch($event, TheliaEvents::SALE_CREATE);

        $sale = $event->getSale();
        self::assertNotNull($sale);
        self::assertSame('Summer sale', $sale->setLocale('en_US')->getTitle());
        self::assertSame('SUMMER', $sale->setLocale('en_US')->getSaleLabel());
    }

    public function testToggleActivityFlipsActiveFlag(): void
    {
        $sale = $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('Promo')->setSaleLabel('PROMO'),
            TheliaEvents::SALE_CREATE,
        )->getSale();

        self::assertSame(0, (int) $sale->getActive(), 'freshly created sales are inactive');

        $this->dispatch(new SaleToggleActivityEvent($sale), TheliaEvents::SALE_TOGGLE_ACTIVITY);

        self::assertSame(1, (int) SaleQuery::create()->findPk($sale->getId())->getActive());
    }

    public function testDeleteRemovesSale(): void
    {
        $sale = $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('To delete')->setSaleLabel('DELETE'),
            TheliaEvents::SALE_CREATE,
        )->getSale();
        $saleId = $sale->getId();

        $this->dispatch(new SaleDeleteEvent($saleId), TheliaEvents::SALE_DELETE);

        self::assertNull(SaleQuery::create()->findPk($saleId));
    }
}
