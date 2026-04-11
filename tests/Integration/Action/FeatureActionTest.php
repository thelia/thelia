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

use Thelia\Core\Event\Feature\FeatureCreateEvent;
use Thelia\Core\Event\Feature\FeatureDeleteEvent;
use Thelia\Core\Event\Feature\FeatureUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\FeatureQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class FeatureActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsFeatureWithI18n(): void
    {
        $event = new FeatureCreateEvent();
        $event->setTitle('Material')->setLocale('en_US')->setAddToAllTemplates(0);

        $this->dispatch($event, TheliaEvents::FEATURE_CREATE);

        $feature = $event->getFeature();
        self::assertNotNull($feature);
        self::assertSame('Material', $feature->setLocale('en_US')->getTitle());
    }

    public function testUpdateReplacesTitle(): void
    {
        $feature = $this->factory->feature(['title' => 'Old']);

        $event = new FeatureUpdateEvent($feature->getId());
        $event
            ->setTitle('Updated')
            ->setLocale('en_US')
            ->setAddToAllTemplates(0)
            ->setDescription('')
            ->setChapo('')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::FEATURE_UPDATE);

        self::assertSame('Updated', FeatureQuery::create()->findPk($feature->getId())->setLocale('en_US')->getTitle());
    }

    public function testDeleteRemovesFeature(): void
    {
        $feature = $this->factory->feature();
        $featureId = $feature->getId();

        $this->dispatch(new FeatureDeleteEvent($featureId), TheliaEvents::FEATURE_DELETE);

        self::assertNull(FeatureQuery::create()->findPk($featureId));
    }
}
