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

use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Thelia\Messenger\FailedMessagePurger;
use Thelia\Messenger\Serializer\AllowedClassesSerializer;
use Thelia\Messenger\Transport\ShopDatabaseTransportFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\ProbeMessage;

/**
 * A job set aside keeps everything it was dispatched with; past the retention, it
 * goes. Played on a queue name of its own in the shop database, written through a
 * connection the test transaction does not cover, and emptied afterwards.
 */
final class FailedMessagePurgerTest extends IntegrationTestCase
{
    private const QUEUE = 'test_failed_message_purger';

    private DoctrineTransport $failureTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $transport = $this->getService(ShopDatabaseTransportFactory::class)->createTransport(
            'doctrine://default?queue_name='.self::QUEUE,
            [],
            $this->getService(AllowedClassesSerializer::class),
        );
        \assert($transport instanceof DoctrineTransport);
        $this->failureTransport = $transport;

        $this->emptyQueue();
    }

    protected function tearDown(): void
    {
        $this->emptyQueue();

        parent::tearDown();
    }

    public function testAJobSetAsideLongerThanTheRetentionIsDeletedAndTheOthersKept(): void
    {
        $this->setAside('forty days ago', new \DateTimeImmutable('-40 days'));
        $this->setAside('five days ago', new \DateTimeImmutable('-5 days'));
        $this->setAside('never dated', null);

        $purged = (new FailedMessagePurger($this->failureTransport))->purgeSetAsideBefore(new \DateTimeImmutable('-30 days'));

        self::assertSame(1, $purged);
        self::assertEqualsCanonicalizing(['five days ago', 'never dated'], $this->labelsLeft());
    }

    public function testADryRunCountsAndDeletesNothing(): void
    {
        $this->setAside('forty days ago', new \DateTimeImmutable('-40 days'));

        $purged = (new FailedMessagePurger($this->failureTransport))->purgeSetAsideBefore(new \DateTimeImmutable('-30 days'), dryRun: true);

        self::assertSame(1, $purged);
        self::assertSame(['forty days ago'], $this->labelsLeft());
    }

    private function setAside(string $label, ?\DateTimeImmutable $setAsideAt): void
    {
        $stamps = null === $setAsideAt ? [] : [new RedeliveryStamp(0, $setAsideAt)];

        $this->failureTransport->send(new Envelope(new ProbeMessage($label), $stamps));
    }

    /**
     * @return list<string>
     */
    private function labelsLeft(): array
    {
        $labels = [];

        foreach ($this->failureTransport->all() as $envelope) {
            $message = $envelope->getMessage();
            \assert($message instanceof ProbeMessage);
            $labels[] = $message->label;
        }

        return $labels;
    }

    private function emptyQueue(): void
    {
        foreach ($this->failureTransport->all() as $envelope) {
            $this->failureTransport->reject($envelope);
        }
    }
}
