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

use Thelia\Core\Event\MailingSystem\MailingSystemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class MailingSystemActionTest extends ActionIntegrationTestCase
{
    public function testUpdatePersistsSmtpConfiguration(): void
    {
        $event = new MailingSystemEvent();
        $event->setEnabled(true);
        $event->setHost('smtp.example.com');
        $event->setPort('587');
        $event->setEncryption('tls');
        $event->setUsername('user@example.com');
        $event->setPassword('secret');
        $event->setAuthMode('login');
        $event->setTimeout('30');
        $event->setSourceIp('127.0.0.1');

        $this->dispatch($event, TheliaEvents::MAILING_SYSTEM_UPDATE);

        self::assertTrue(ConfigQuery::isSmtpEnable());
        self::assertSame('smtp.example.com', ConfigQuery::getSmtpHost());
        self::assertSame('587', ConfigQuery::getSmtpPort());
        self::assertSame('tls', ConfigQuery::getSmtpEncryption());
        self::assertSame('user@example.com', ConfigQuery::getSmtpUsername());
        self::assertSame('secret', ConfigQuery::getSmtpPassword());
        self::assertSame('login', ConfigQuery::getSmtpAuthMode());
        self::assertSame('30', ConfigQuery::getSmtpTimeout());
        self::assertSame('127.0.0.1', ConfigQuery::getSmtpSourceIp());
    }

    public function testUpdateDisablesSmtpWhenEnabledIsFalse(): void
    {
        // First enable SMTP.
        ConfigQuery::enableSmtp();
        self::assertTrue(ConfigQuery::isSmtpEnable());

        $event = new MailingSystemEvent();
        $event->setEnabled(false);
        $event->setHost('localhost');
        $event->setPort('25');
        $event->setEncryption('');
        $event->setUsername('');
        $event->setPassword('');
        $event->setAuthMode('');
        $event->setTimeout('30');
        $event->setSourceIp('');

        $this->dispatch($event, TheliaEvents::MAILING_SYSTEM_UPDATE);

        self::assertFalse(ConfigQuery::isSmtpEnable());
    }
}
