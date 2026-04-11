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

use Thelia\Core\Event\Attribute\AttributeCreateEvent;
use Thelia\Core\Event\Attribute\AttributeDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AttributeQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class AttributeActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsAttributeWithI18n(): void
    {
        $event = new AttributeCreateEvent();
        $event->setTitle('Color')->setLocale('en_US')->setAddToAllTemplates(0);

        $this->dispatch($event, TheliaEvents::ATTRIBUTE_CREATE);

        $attribute = $event->getAttribute();
        self::assertNotNull($attribute);
        self::assertSame('Color', $attribute->setLocale('en_US')->getTitle());
        self::assertGreaterThan(0, $attribute->getPosition());
    }

    public function testUpdateReplacesTitle(): void
    {
        $attribute = $this->factory->attribute(['title' => 'Old']);

        $event = new AttributeUpdateEvent($attribute->getId());
        $event
            ->setTitle('Updated')
            ->setLocale('en_US')
            ->setAddToAllTemplates(0)
            ->setDescription('')
            ->setChapo('')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::ATTRIBUTE_UPDATE);

        $reloaded = AttributeQuery::create()->findPk($attribute->getId());
        self::assertSame('Updated', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testDeleteRemovesAttribute(): void
    {
        $attribute = $this->factory->attribute();
        $attributeId = $attribute->getId();

        $this->dispatch(new AttributeDeleteEvent($attributeId), TheliaEvents::ATTRIBUTE_DELETE);

        self::assertNull(AttributeQuery::create()->findPk($attributeId));
    }
}
