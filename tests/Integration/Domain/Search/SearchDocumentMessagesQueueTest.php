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

namespace Thelia\Tests\Integration\Domain\Search;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Thelia\Command\SearchReindexCommand;
use Thelia\Domain\Search\Message\IndexDocument;
use Thelia\Domain\Search\Message\RemoveDocument;
use Thelia\Messenger\Message\UndecodableJob;
use Thelia\Test\IntegrationTestCase;

final class SearchDocumentMessagesQueueTest extends IntegrationTestCase
{
    public function testAQueuedSynchronizationIsReadBackAsItWasSent(): void
    {
        $serializer = static::getContainer()->get('messenger.default_serializer');
        \assert($serializer instanceof SerializerInterface);
        $sent = new IndexDocument('product', [3, 1, 2], ['en_US', 'fr_FR']);

        $read = $serializer->decode($serializer->encode(new Envelope($sent, [new TransportNamesStamp([SearchReindexCommand::QUEUE_TRANSPORT])])));

        self::assertNotInstanceOf(UndecodableJob::class, $read->getMessage());
        self::assertEquals($sent, $read->getMessage());
        self::assertSame([SearchReindexCommand::QUEUE_TRANSPORT], $read->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testASynchronizationOfEveryLocaleIsReadBackWithoutLocales(): void
    {
        $serializer = static::getContainer()->get('messenger.default_serializer');
        \assert($serializer instanceof SerializerInterface);
        $sent = new IndexDocument('brand', [7]);

        self::assertEquals($sent, $serializer->decode($serializer->encode(new Envelope($sent)))->getMessage());
    }

    public function testAQueuedRemovalIsReadBackAsItWasSent(): void
    {
        $serializer = static::getContainer()->get('messenger.default_serializer');
        \assert($serializer instanceof SerializerInterface);
        $sent = new RemoveDocument('product', [4, 2]);

        $read = $serializer->decode($serializer->encode(new Envelope($sent)))->getMessage();

        self::assertNotInstanceOf(UndecodableJob::class, $read);
        self::assertEquals($sent, $read);
    }
}
