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

use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\MetaDataQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class MetaDataActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsMetaData(): void
    {
        $category = $this->factory->category();
        $event = new MetaDataCreateOrUpdateEvent(
            metaKey: 'custom_seo_score',
            elementKey: 'category',
            elementId: $category->getId(),
            value: '42',
        );

        $this->dispatch($event, TheliaEvents::META_DATA_CREATE);

        $metaData = $event->getMetaData();
        self::assertNotNull($metaData);
        self::assertSame('custom_seo_score', $metaData->getMetaKey());
        self::assertSame((string) 42, $metaData->getValue());
    }

    public function testUpdateReplacesExistingValue(): void
    {
        $category = $this->factory->category();
        $this->dispatch(
            new MetaDataCreateOrUpdateEvent('custom_key', 'category', $category->getId(), 'old'),
            TheliaEvents::META_DATA_CREATE,
        );

        $update = new MetaDataCreateOrUpdateEvent('custom_key', 'category', $category->getId(), 'new');
        $this->dispatch($update, TheliaEvents::META_DATA_UPDATE);

        $reloaded = MetaDataQuery::create()
            ->filterByMetaKey('custom_key')
            ->filterByElementKey('category')
            ->filterByElementId($category->getId())
            ->findOne();
        self::assertNotNull($reloaded);
        self::assertSame('new', $reloaded->getValue());
    }

    public function testDeleteRemovesMetaData(): void
    {
        $category = $this->factory->category();
        $this->dispatch(
            new MetaDataCreateOrUpdateEvent('gone_key', 'category', $category->getId(), 'bye'),
            TheliaEvents::META_DATA_CREATE,
        );

        $this->dispatch(
            new MetaDataDeleteEvent('gone_key', 'category', $category->getId()),
            TheliaEvents::META_DATA_DELETE,
        );

        self::assertNull(
            MetaDataQuery::create()
                ->filterByMetaKey('gone_key')
                ->filterByElementKey('category')
                ->filterByElementId($category->getId())
                ->findOne(),
        );
    }
}
