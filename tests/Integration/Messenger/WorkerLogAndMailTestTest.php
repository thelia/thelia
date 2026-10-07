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

namespace Thelia\Tests\Integration\Messenger;

use Monolog\Logger;
use Symfony\Component\Mailer\Envelope as MailEnvelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Messenger\Log\FailedJobLogProcessor;
use Thelia\Test\IntegrationTestCase;

/**
 * The worker's log and the test of the mail configuration, as the container wires them.
 */
final class WorkerLogAndMailTestTest extends IntegrationTestCase
{
    public function testTheLogOfTheWorkersGoesThroughTheFailedJobProcessor(): void
    {
        $logger = static::getContainer()->get('monolog.logger.messenger');
        self::assertInstanceOf(Logger::class, $logger);

        self::assertNotSame([], array_filter($logger->getProcessors(), static fn (callable $processor): bool => $processor instanceof FailedJobLogProcessor));
    }

    /**
     * A test of the mail configuration goes to the mail server, not to the queue: its
     * whole point is the server's answer.
     */
    public function testAMailSentNowSkipsTheQueue(): void
    {
        $queued = new class implements MailerInterface {
            public int $sent = 0;

            public function send(RawMessage $message, ?MailEnvelope $envelope = null): void
            {
                ++$this->sent;
            }
        };
        $server = new class implements TransportInterface {
            public int $sent = 0;

            public function send(RawMessage $message, ?MailEnvelope $envelope = null): ?SentMessage
            {
                ++$this->sent;

                return null;
            }

            public function __toString(): string
            {
                return 'probe://';
            }
        };
        $factory = new MailerFactory($this->getService(TemplateHelperInterface::class), $this->getService(ParserResolver::class), $queued, $server);

        $factory->sendNow((new Email())->from('shop@example.com')->to('admin@example.com')->text('Test'));

        self::assertSame(1, $server->sent);
        self::assertSame(0, $queued->sent);
    }
}
