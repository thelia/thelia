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

namespace Thelia\Tests\Unit\Mailer;

use PHPUnit\Framework\TestCase;
use Thelia\Mailer\MailerFactory;

/**
 * What the mailer writes to the log when the transport refuses a message.
 *
 * A Symfony transport quotes the DSN it was configured with, password included,
 * in the reason it gives. That reason goes to the server log, which is read,
 * shipped and archived far more widely than the configuration it comes from.
 */
final class MailerFactoryTransportCredentialsTest extends TestCase
{
    public function testThePasswordOfTheTransportIsHiddenBeforeTheReasonIsLogged(): void
    {
        $logged = $this->sanitize('Connection to smtp://postmaster:s3cr3t@mail.example.com:587 refused');

        self::assertStringNotContainsString('s3cr3t', $logged);
        self::assertStringNotContainsString('postmaster', $logged);
        self::assertStringContainsString('smtp://***@mail.example.com:587', $logged, 'The host stays readable: that is what a shop needs to fix the failure.');
    }

    public function testEveryTransportOfAFailoverDsnIsCleaned(): void
    {
        $logged = $this->sanitize('failover(smtp://first:pass1@a.example.com smtp://second:pass2@b.example.com) unavailable');

        self::assertStringNotContainsString('pass1', $logged);
        self::assertStringNotContainsString('pass2', $logged);
    }

    public function testAReasonWithNoCredentialsIsLeftAsItIs(): void
    {
        $reason = 'Unable to write body to stream, mailbox unavailable for contact@example.com';

        self::assertSame($reason, $this->sanitize($reason));
    }

    private function sanitize(string $message): string
    {
        $method = new \ReflectionMethod(MailerFactory::class, 'withoutTransportCredentials');

        return $method->invoke(null, $message);
    }
}
