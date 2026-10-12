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

use Psr\Cache\CacheItemPoolInterface;
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
 * A queue may hand a mail job out twice: when the worker that sent it stopped before
 * acknowledging it. The customer receives it once.
 */
final class SentMailGuardTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $this->sentMails()->clear();

        parent::tearDown();
    }

    public function testAMailAWorkerSentAlreadyIsNotSentAgain(): void
    {
        $transport = new NullTransport($this->getService(EventDispatcherInterface::class));
        $mailId = SentMailGuardListener::newMailId();

        $this->inAWorker(static function () use ($transport, $mailId): void {
            $first = $transport->send(self::mailWith($mailId));
            self::assertNotNull($first);
            self::assertFalse($first->getOriginalMessage() instanceof Email && $first->getOriginalMessage()->getHeaders()->has(SentMailGuardListener::MAIL_ID_HEADER), 'The customer never receives the identifier.');
        });

        $this->inAWorker(static function () use ($transport, $mailId): void {
            self::assertNull($transport->send(self::mailWith($mailId)), 'The same mail handed out again is not sent.');
            self::assertNotNull($transport->send(self::mailWith(SentMailGuardListener::newMailId())), 'Another mail is.');
        });
    }

    public function testWithoutAWorkerAMailIsSentAndNothingIsKept(): void
    {
        $transport = new NullTransport($this->getService(EventDispatcherInterface::class));
        $mailId = SentMailGuardListener::newMailId();

        self::assertNotNull($transport->send(self::mailWith($mailId)));
        self::assertNotNull($transport->send(self::mailWith($mailId)));
        self::assertFalse($this->sentMails()->hasItem('sent_mail.'.$mailId));
    }

    public function testTheFactoryGivesEveryMailItsOwnIdentifierAndKeepsOneItHas(): void
    {
        $mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
        $factory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $mailer,
            new NullTransport(),
        );
        $kept = SentMailGuardListener::newMailId();

        $factory->send(self::mailWith(null));
        $factory->send(self::mailWith(null));
        $factory->send(self::mailWith($kept));

        $ids = array_map(static fn (RawMessage $message): string => $message instanceof Email ? (string) $message->getHeaders()->get(SentMailGuardListener::MAIL_ID_HEADER)?->getBodyAsString() : '', $mailer->sent);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $ids[0]);
        self::assertNotSame($ids[0], $ids[1]);
        self::assertSame($kept, $ids[2]);
    }

    /**
     * Runs $send as a worker handles a job: between the job received and the job handled.
     */
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

    private static function mailWith(?string $mailId): Email
    {
        $mail = (new Email())->from('shop@example.com')->to('customer@example.com')->subject('Your order')->text('Thank you.');

        if (null !== $mailId) {
            $mail->getHeaders()->addTextHeader(SentMailGuardListener::MAIL_ID_HEADER, $mailId);
        }

        return $mail;
    }

    private function sentMails(): CacheItemPoolInterface
    {
        return $this->getService('thelia.cache.sent_mails');
    }
}
