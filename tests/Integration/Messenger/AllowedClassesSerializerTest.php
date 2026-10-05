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

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Mime\Email;
use Thelia\Messenger\Serializer\AllowedClassesSerializer;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\InnerSerializerSpy;
use Thelia\Tests\Support\Messenger\ProbeMessage;

/**
 * Whoever can write to a queue chooses the classes it holds: nothing is built from
 * an envelope before its message and stamp classes have been checked. The inner
 * serializer below records whether it was reached, which is the point: a refused
 * envelope never gets as far as being read.
 */
final class AllowedClassesSerializerTest extends IntegrationTestCase
{
    private InnerSerializerSpy $inner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inner = new InnerSerializerSpy();
    }

    public function testTheMailOfTheShopIsLetThrough(): void
    {
        $this->serializer()->decode($this->encoded(SendEmailMessage::class));

        self::assertSame(1, $this->inner->decoded);
    }

    public function testAClassOfTheCoreIsLetThrough(): void
    {
        $this->serializer()->decode($this->encoded(ProbeMessage::class));

        self::assertSame(1, $this->inner->decoded);
    }

    /**
     * Symfony ships handlers that run a console command or a process: a queue that
     * could carry their messages would run whatever its writer wanted.
     */
    public function testASymfonyMessageThatRunsACommandIsRefusedBeforeAnythingIsBuilt(): void
    {
        try {
            $this->serializer()->decode($this->encoded(RunCommandMessage::class));
            self::fail('A command message must not be read off a queue.');
        } catch (MessageDecodingFailedException) {
        }

        self::assertSame(0, $this->inner->decoded);
    }

    public function testAClassOfAnActiveModuleIsLetThrough(): void
    {
        $module = $this->activeModuleCode();

        $this->serializer()->decode($this->encoded($module.'\\Message\\SomeJob'));

        self::assertSame(1, $this->inner->decoded);
    }

    public function testAClassOfAModuleTurnedOffIsRefused(): void
    {
        $module = $this->activeModuleCode();
        ModuleQuery::create()->findOneByCode($module)
            ?->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->save($this->getPropelConnection());

        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode($this->encoded($module.'\\Message\\SomeJob'));
    }

    public function testAStampCarryingSerializerContextIsRefused(): void
    {
        $encoded = $this->encoded(SendEmailMessage::class);
        $encoded['headers']['X-Message-Stamp-'.SerializerStamp::class] = '[{"context":{}}]';

        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode($encoded);
    }

    public function testAStampOfAnUnknownClassIsRefused(): void
    {
        $encoded = $this->encoded(SendEmailMessage::class);
        $encoded['headers']['X-Message-Stamp-Some\\Vendor\\Stamp'] = '[{}]';

        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode($encoded);
    }

    public function testTheStampsOfMessengerAreLetThrough(): void
    {
        $encoded = $this->encoded(SendEmailMessage::class);
        $encoded['headers']['X-Message-Stamp-'.BusNameStamp::class] = '[{"busName":"messenger.bus.default"}]';

        $this->serializer()->decode($encoded);

        self::assertSame(1, $this->inner->decoded);
    }

    /**
     * Refused when it is queued too, so that the module dispatching it learns it at
     * once rather than from a queue that never empties.
     */
    public function testAMessageTheWorkersWouldRefuseCannotBeQueued(): void
    {
        $this->expectException(\LogicException::class);

        $this->serializer()->encode(new Envelope(new RunCommandMessage('cache:clear')));
    }

    public function testTheShopMailCanBeQueued(): void
    {
        $this->serializer()->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->text('Hello'))));

        self::assertSame(1, $this->inner->encoded);
    }

    public function testAProjectCanLetAClassThrough(): void
    {
        (new AllowedClassesSerializer($this->inner, [RunCommandMessage::class]))->decode($this->encoded(RunCommandMessage::class));

        self::assertSame(1, $this->inner->decoded);
    }

    /**
     * Every transport reads and writes through it: Symfony wraps it in its own
     * signing serializer, so it is checked by what it refuses.
     */
    public function testTheSerializerOfTheQueuesGoesThroughTheAllowList(): void
    {
        $serializer = static::getContainer()->get('messenger.default_serializer');
        \assert($serializer instanceof SerializerInterface);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not one the shop queues');

        $serializer->encode(new Envelope(new RunCommandMessage('cache:clear')));
    }

    private function serializer(): AllowedClassesSerializer
    {
        return new AllowedClassesSerializer($this->inner);
    }

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    private function encoded(string $type): array
    {
        return ['body' => '{}', 'headers' => ['type' => $type, 'Content-Type' => 'application/json']];
    }

    private function activeModuleCode(): string
    {
        $module = ModuleQuery::create()->filterByActivate(BaseModule::IS_ACTIVATED)->findOne($this->getPropelConnection());

        if (null === $module) {
            self::markTestSkipped('The test database has no active module.');
        }

        return $module->getCode();
    }
}
