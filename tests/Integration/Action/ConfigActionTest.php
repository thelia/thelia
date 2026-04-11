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

use Thelia\Core\Event\Config\ConfigCreateEvent;
use Thelia\Core\Event\Config\ConfigDeleteEvent;
use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ConfigActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsConfigEntry(): void
    {
        $event = new ConfigCreateEvent();
        $event
            ->setEventName('test_config')
            ->setValue('42')
            ->setLocale('en_US')
            ->setTitle('Test config')
            ->setHidden(false)
            ->setSecured(false);

        $this->dispatch($event, TheliaEvents::CONFIG_CREATE);

        $config = $event->getConfig();
        self::assertNotNull($config);
        self::assertSame('test_config', $config->getName());
        self::assertSame('42', $config->getValue());
        self::assertSame('Test config', $config->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesValueAndI18n(): void
    {
        $existing = $this->dispatch(
            (new ConfigCreateEvent())
                ->setEventName('updatable_key')
                ->setValue('old')
                ->setLocale('en_US')
                ->setTitle('Old')
                ->setHidden(false)
                ->setSecured(false),
            TheliaEvents::CONFIG_CREATE,
        )->getConfig();

        $event = new ConfigUpdateEvent($existing->getId());
        $event
            ->setEventName('updatable_key')
            ->setValue('new')
            ->setLocale('en_US')
            ->setTitle('New')
            ->setHidden(false)
            ->setSecured(false)
            ->setDescription('')
            ->setChapo('')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::CONFIG_UPDATE);

        $reloaded = ConfigQuery::create()->findPk($existing->getId());
        self::assertSame('new', $reloaded->getValue());
        self::assertSame('New', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testDeleteRemovesConfigEntry(): void
    {
        $existing = $this->dispatch(
            (new ConfigCreateEvent())
                ->setEventName('temporary_key')
                ->setValue('v')
                ->setLocale('en_US')
                ->setTitle('Temporary')
                ->setHidden(false)
                ->setSecured(false),
            TheliaEvents::CONFIG_CREATE,
        )->getConfig();
        $configId = $existing->getId();

        $this->dispatch(new ConfigDeleteEvent($configId), TheliaEvents::CONFIG_DELETE);

        self::assertNull(ConfigQuery::create()->findPk($configId));
    }
}
