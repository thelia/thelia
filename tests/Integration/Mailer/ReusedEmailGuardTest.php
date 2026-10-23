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

namespace Thelia\Tests\Integration\Mailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Messenger\Envelope as MessengerEnvelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\EventListener\SentMailGuardListener;
use Thelia\Mailer\MailerFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * MailerFactory::send() writes its identifier into the Email the caller gave, and keeps
 * one an Email already carries: a caller that sends the same Email object twice (a
 * different recipient each time) queues two mails with the same identifier, and the
 * worker drops the second one as "already sent".
 */
final class ReusedEmailGuardTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $this->getService('thelia.cache.sent_mails')->clear();

        parent::tearDown();
    }

    public function testTwoSendsOfTheSameEmailObjectToTwoRecipientsBothLeaveTheWorker(): void
    {
        // The queue keeps a copy of each mail, as Messenger serializes it.
        $queue = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $queued = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->queued[] = clone $message;
            }
        };
        $factory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $queue,
            new NullTransport(),
        );

        $email = (new Email())->from('shop@example.com')->to('first@example.com')->subject('News')->text('Hello.');
        $factory->send($email);
        $email->to('second@example.com');
        $factory->send($email);

        self::assertCount(2, $queue->queued);

        $transport = new NullTransport($this->getService(EventDispatcherInterface::class));
        $delivered = [];

        foreach ($queue->queued as $queuedMail) {
            $this->inAWorker(static function () use ($transport, $queuedMail, &$delivered): void {
                $sent = $transport->send($queuedMail);

                if (null !== $sent) {
                    $delivered[] = $sent->getEnvelope()->getRecipients()[0]->getAddress();
                }
            });
        }

        self::assertSame(['first@example.com', 'second@example.com'], $delivered, 'Two sends to two recipients are two mails: the worker must deliver both.');
    }

    private function inAWorker(\Closure $send): void
    {
        $guard = $this->getService(SentMailGuardListener::class);
        $job = new MessengerEnvelope(new \stdClass());
        $guard->onWorkerMessageReceived(new WorkerMessageReceivedEvent($job, 'async'));

        try {
            $send();
        } finally {
            $guard->onWorkerMessageHandled(new WorkerMessageHandledEvent($job, 'async'));
        }
    }
}
