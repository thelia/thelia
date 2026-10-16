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

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Mime\Email;
use Thelia\Mailer\EventListener\OrderEmailHistoryListener;
use Thelia\Messenger\Serializer\AllowedClassesSerializer;
use Thelia\Messenger\Transport\ShopDatabaseTransportFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The queue a shop gets without any server of its own: a table of its database,
 * reached with the settings of the Propel connection.
 *
 * The transport writes through a connection of its own, so what it writes is
 * committed whatever the test transaction does: every test works on a queue name
 * nothing else uses and empties it afterwards.
 */
final class ShopDatabaseTransportTest extends IntegrationTestCase
{
    private const QUEUE = 'test_shop_database_transport';

    private DoctrineTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $transport = $this->getService(ShopDatabaseTransportFactory::class)->createTransport(
            'doctrine://default?queue_name='.self::QUEUE,
            [],
            $this->getService(AllowedClassesSerializer::class),
        );
        \assert($transport instanceof DoctrineTransport);
        $this->transport = $transport;

        $this->emptyQueue();
    }

    protected function tearDown(): void
    {
        $this->emptyQueue();

        parent::tearDown();
    }

    public function testAQueuedMailComesBackAsTheMailThatWasQueued(): void
    {
        $email = (new Email())
            ->from('shop@example.com')
            ->to('buyer@example.com')
            ->subject('Your order ORD000000000042')
            ->html('<p>Thank you for your order.</p>')
            ->text('Thank you for your order.');
        $email->getHeaders()->addTextHeader(OrderEmailHistoryListener::ORDER_ID_HEADER, '42');

        $this->transport->send(new Envelope(new SendEmailMessage($email)));

        self::assertSame(1, $this->transport->getMessageCount());

        $received = iterator_to_array($this->transport->get());
        self::assertCount(1, $received);

        $message = $received[0]->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $message);
        $receivedEmail = $message->getMessage();
        self::assertInstanceOf(Email::class, $receivedEmail);
        self::assertSame('Your order ORD000000000042', $receivedEmail->getSubject());
        self::assertSame('<p>Thank you for your order.</p>', $receivedEmail->getHtmlBody());
        self::assertSame('buyer@example.com', $receivedEmail->getTo()[0]->getAddress());
        self::assertSame('42', $receivedEmail->getHeaders()->get(OrderEmailHistoryListener::ORDER_ID_HEADER)?->getBodyAsString());

        $this->transport->ack($received[0]);

        self::assertSame(0, $this->transport->getMessageCount());
    }

    /**
     * Taken by a worker, a job is hidden from the other workers until it is
     * acknowledged or given back: two workers never run the same job at once.
     */
    public function testAJobTakenByAWorkerIsNotHandedToAnother(): void
    {
        $this->transport->send(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->text('Hello'))));

        $taken = iterator_to_array($this->transport->get());
        self::assertCount(1, $taken);
        self::assertCount(0, iterator_to_array($this->transport->get()));

        // A job being worked on is not listed, so it is acknowledged here rather than
        // left for the clean up.
        $this->transport->ack($taken[0]);
    }

    /**
     * The queue has a connection of its own: a job dispatched inside a Propel
     * transaction stays queued when that transaction is rolled back. This is why a job
     * is dispatched once the writes it is about are committed.
     */
    public function testAJobQueuedInsideARolledBackTransactionStaysQueued(): void
    {
        $connection = $this->getPropelConnection();
        $connection->beginTransaction();
        $this->transport->send(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->text('Hello'))));
        $connection->rollBack();

        self::assertSame(1, $this->transport->getMessageCount());
    }

    private function emptyQueue(): void
    {
        foreach ($this->transport->all() as $envelope) {
            $this->transport->reject($envelope);
        }
    }
}
