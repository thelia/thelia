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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\MessagePart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Scheduler\Messenger\ServiceCallMessage;
use Thelia\Core\DependencyInjection\Compiler\HandledMessageClassesPass;
use Thelia\Domain\DataTransfer\Job\DataTransferJobMessage;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Messenger\Message\UndecodableJob;
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
     * could carry their messages would run whatever its writer wanted. Such a job is
     * never built: what reaches the serializer is an UndecodableJob saying what it was.
     */
    public function testASymfonyMessageThatRunsACommandIsNeverBuilt(): void
    {
        $this->serializer()->decode($this->encoded(RunCommandMessage::class));

        $this->assertReadAsUndecodable(RunCommandMessage::class);
    }

    public function testAClassOfAnActiveModuleIsLetThrough(): void
    {
        $module = $this->activeModuleCode();

        $this->serializer()->decode($this->encoded($module.'\\Message\\SomeJob'));

        self::assertSame(1, $this->inner->decoded);
        self::assertSame($module.'\\Message\\SomeJob', $this->inner->lastDecoded['headers']['type'] ?? null);
    }

    /**
     * A job of a module turned off is kept, as an UndecodableJob, instead of being
     * deleted by Symfony as a message it cannot read.
     */
    public function testAClassOfAModuleTurnedOffIsKeptAsAnUnreadableJob(): void
    {
        $module = $this->activeModuleCode();
        ModuleQuery::create()->findOneByCode($module)
            ?->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->save($this->getPropelConnection());

        $this->serializer()->decode($this->encoded($module.'\\Message\\SomeJob'));

        $this->assertReadAsUndecodable($module.'\\Message\\SomeJob');
    }

    public function testAStampCarryingSerializerContextIsNeverRead(): void
    {
        $header = 'X-Message-Stamp-'.SerializerStamp::class;
        $encoded = $this->encoded(SendEmailMessage::class);
        $encoded['headers'][$header] = '[{"context":{}}]';

        $this->serializer()->decode($encoded);

        $this->assertReadAsUndecodable(SendEmailMessage::class);
        self::assertArrayNotHasKey($header, $this->inner->lastDecoded['headers'] ?? []);
    }

    public function testAStampOfAnUnknownClassIsNeverRead(): void
    {
        $header = 'X-Message-Stamp-Some\\Vendor\\Stamp';
        $encoded = $this->encoded(SendEmailMessage::class);
        $encoded['headers'][$header] = '[{}]';

        $this->serializer()->decode($encoded);

        $this->assertReadAsUndecodable(SendEmailMessage::class);
        self::assertArrayNotHasKey($header, $this->inner->lastDecoded['headers'] ?? []);
    }

    /**
     * The stamps the shop reads survive, so the failure keeps its date and attempts.
     */
    public function testAnUnreadableJobKeepsTheMessengerStamps(): void
    {
        $header = 'X-Message-Stamp-'.BusNameStamp::class;
        $encoded = $this->encoded(RunCommandMessage::class);
        $encoded['headers'][$header] = '[{"busName":"messenger.bus.default"}]';

        $this->serializer()->decode($encoded);

        self::assertArrayHasKey($header, $this->inner->lastDecoded['headers'] ?? []);
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
        (new AllowedClassesSerializer($this->inner, [ProbeMessage::class], []))->decode($this->encoded(ProbeMessage::class));

        self::assertSame(1, $this->inner->decoded);
    }

    /**
     * Listed or not: a message that runs a command on the server is never built from a
     * queue, or whoever writes to the queue runs anything.
     */
    #[DataProvider('messagesThatRunSomethingOnTheServer')]
    public function testAMessageThatRunsACommandIsNeverBuiltEvenWhenListed(string $class): void
    {
        (new AllowedClassesSerializer($this->inner, [$class], []))->decode($this->encoded($class));

        $this->assertReadAsUndecodable($class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function messagesThatRunSomethingOnTheServer(): iterable
    {
        yield 'a console command' => [RunCommandMessage::class];
        // Calls any public method of a recurring task service, with any arguments.
        yield 'a call to a scheduled service' => [ServiceCallMessage::class];
        // Computes a cache value through a service the message names.
        yield 'an early expiration of a cache item' => [EarlyExpirationMessage::class];
        // PHP finds a class whatever the case of its name.
        yield 'a console command named in lower case' => [strtolower(RunCommandMessage::class)];
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

    /**
     * Through the serializer the queues really use: the refused job comes back as an
     * UndecodableJob that still carries its attempts.
     */
    public function testTheQueuesReadARefusedJobAsAnUnreadableOneWithItsAttempts(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);

        $envelope = $serializer->decode([
            'body' => '{"input":"cache:clear"}',
            'headers' => [
                'type' => RunCommandMessage::class,
                'X-Message-Stamp-'.RedeliveryStamp::class => '[{"retryCount":3,"redeliveredAt":"2026-10-01T10:00:00+00:00"}]',
                'Content-Type' => 'application/json',
            ],
        ]);

        $message = $envelope->getMessage();
        self::assertInstanceOf(UndecodableJob::class, $message);
        self::assertSame(RunCommandMessage::class, $message->originalType);
        self::assertSame('{"input":"cache:clear"}', $message->originalBody);
        self::assertSame(3, $envelope->last(RedeliveryStamp::class)?->getRetryCount());
    }

    /**
     * A mail part may name a file to read when the mail is sent: written into a queue,
     * it would have the worker mail any file of the server to any address.
     */
    public function testAQueuedMailNeverReadsAFileOfTheServer(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->attach('PLACEHOLDER', 'invoice.txt', 'text/plain'))));
        $forged = str_replace('"body":"PLACEHOLDER"', '"body":'.json_encode(['path' => __FILE__, 'contentType' => 'text/plain', 'size' => null, 'filename' => 'invoice.txt'], \JSON_THROW_ON_ERROR), $encoded['body'], $replaced);
        self::assertSame(1, $replaced, 'The body of the attachment is where the test expects it.');

        $message = $serializer->decode(['body' => $forged, 'headers' => $encoded['headers']])->getMessage();

        if ($message instanceof SendEmailMessage) {
            foreach ($message->getMessage() instanceof Email ? $message->getMessage()->getAttachments() : [] as $attachment) {
                self::assertStringNotContainsString('testAQueuedMailNeverReadsAFileOfTheServer', $attachment->getBody(), 'The worker read a file of the server into the mail.');
            }
        }

        self::assertInstanceOf(UndecodableJob::class, $message);
    }

    /**
     * A part built from a path looks at that path at once, and a folder or an unreadable
     * file would refuse to be built: the reason kept with the job would then tell
     * whether the path exists. The file is told from the JSON, before any part is built.
     */
    public function testAFileNamedInAQueuedMailIsToldBeforeAnyPartIsBuilt(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->attach('PLACEHOLDER', 'invoice.txt', 'text/plain'))));
        $forged = str_replace('"body":"PLACEHOLDER"', '"body":'.json_encode(['path' => sys_get_temp_dir(), 'contentType' => 'text/plain', 'size' => null, 'filename' => 'invoice.txt'], \JSON_THROW_ON_ERROR), $encoded['body'], $replaced);
        self::assertSame(1, $replaced);

        $message = $serializer->decode(['body' => $forged, 'headers' => $encoded['headers']])->getMessage();

        self::assertInstanceOf(UndecodableJob::class, $message);
        self::assertStringContainsString('names no file of the server', $message->reason);
    }

    /**
     * A mail carried as the body of a mail has parts of its own: a file named there is
     * never read either.
     */
    public function testAMailCarriedByAQueuedMailNeverReadsAFileOfTheServer(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $carried = (new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Carried')->text('Hi')->attach('PLACEHOLDER', 'invoice.txt', 'text/plain');
        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->setBody(new MessagePart($carried)))));
        $forged = str_replace('"body":"PLACEHOLDER"', '"body":'.json_encode(['path' => __FILE__, 'contentType' => 'text/plain', 'size' => null, 'filename' => 'invoice.txt'], \JSON_THROW_ON_ERROR), $encoded['body'], $replaced);
        self::assertSame(1, $replaced, 'The body of the carried attachment is where the test expects it.');

        $message = $serializer->decode(['body' => $forged, 'headers' => $encoded['headers']])->getMessage();

        if ($message instanceof SendEmailMessage) {
            self::assertStringNotContainsString(base64_encode(substr((string) file_get_contents(__FILE__), 0, 300)), str_replace("\r\n", '', $message->getMessage()->toString()), 'The worker read a file of the server into the mail.');
        }

        self::assertInstanceOf(UndecodableJob::class, $message);
    }

    /**
     * An attachment given by its path is queued with its content: the worker reads no
     * file, and may run on another server than the one the file is on.
     */
    public function testAMailAttachingAFileIsQueuedWithItsContent(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);

        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Invoice')->attachFromPath(__FILE__, 'invoice.txt', 'text/plain'))));

        self::assertStringNotContainsString('"path"', $encoded['body']);

        $message = $serializer->decode($encoded)->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $message);
        $email = $message->getMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertCount(1, $email->getAttachments());
        self::assertSame(file_get_contents(__FILE__), $email->getAttachments()[0]->getBody());
        self::assertSame('invoice.txt', $email->getAttachments()[0]->getFilename());
    }

    /**
     * What Symfony says of content it cannot read may quote that content: the reason
     * kept with the job says only that it no longer fits its class.
     */
    public function testAJobWhoseContentNoLongerFitsItsClassQuotesNoneOfIt(): void
    {
        $message = $this->getService(AllowedClassesSerializer::class)->decode([
            'body' => '{"exportJobId":"buyer@example.com"}',
            'headers' => ['type' => RunExportJob::class, 'Content-Type' => 'application/json'],
        ])->getMessage();

        self::assertInstanceOf(UndecodableJob::class, $message);
        self::assertStringNotContainsString('exportJobId', $message->reason);
        self::assertStringContainsString(RunExportJob::class, $message->reason);
    }

    /**
     * Content Symfony builds into objects may refuse to be built in its own words (an
     * address that is not one): the job is kept as unreadable, the worker goes on.
     */
    public function testAJobWhoseContentCannotBeBuiltIsKeptAsAnUnreadableJob(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->text('Hi'))));
        $forged = str_replace('"address":"buyer@example.com"', '"address":"not an address"', $encoded['body'], $replaced);
        self::assertSame(1, $replaced, 'The address is where the test expects it.');

        $message = $serializer->decode(['body' => $forged, 'headers' => $encoded['headers']])->getMessage();

        self::assertInstanceOf(UndecodableJob::class, $message);
    }

    /**
     * A mail of a class the shop does not send may do more when it is sent than send
     * itself (a templated mail renders a template the queue would choose).
     */
    public function testAQueuedMailOfAClassTheShopDoesNotSendIsNeverRead(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->text('Hi'))));
        $position = strrpos($encoded['body'], '"class":"Symfony\\\\Component\\\\Mime\\\\Email"');
        self::assertNotFalse($position, 'The class of the mail is where the test expects it.');
        $forged = substr_replace($encoded['body'], '"htmlTemplate":"email/default/order_confirmation.html.twig","class":'.json_encode(TemplatedEmail::class, \JSON_THROW_ON_ERROR), $position, \strlen('"class":"Symfony\\\\Component\\\\Mime\\\\Email"'));

        $message = $serializer->decode(['body' => $forged, 'headers' => $encoded['headers']])->getMessage();

        self::assertInstanceOf(UndecodableJob::class, $message);
    }

    /**
     * A templated mail rendered before it is sent renders nothing more in the worker: it
     * is queued as any other.
     */
    public function testARenderedTemplatedMailIsQueued(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $mail = (new TemplatedEmail())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->text('Rendered');
        $mail->markAsRendered();

        $message = $serializer->decode($serializer->encode(new Envelope(new SendEmailMessage($mail))))->getMessage();

        self::assertInstanceOf(SendEmailMessage::class, $message);
        self::assertInstanceOf(TemplatedEmail::class, $message->getMessage());
    }

    /**
     * Whatever a part of a forged mail throws when it is looked at, the job is kept as
     * unreadable and the worker goes on.
     */
    public function testAMailPartThatThrowsWhenLookedAtIsKeptAsAnUnreadableJob(): void
    {
        $serializer = $this->getService(AllowedClassesSerializer::class);
        $encoded = $serializer->encode(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Form')->setBody(new FormDataPart(['field' => 'PLACEHOLDER'])))));
        $forged = str_replace(['"field":"PLACEHOLDER"', '"boundary":null'], ['"field":5', '"boundary":"forged"'], $encoded['body'], $replaced);
        self::assertSame(2, $replaced, 'The field and the boundary are where the test expects them.');
        $message = $serializer->decode(['body' => $forged, 'headers' => $encoded['headers']])->getMessage();

        self::assertInstanceOf(UndecodableJob::class, $message);
    }

    public function testAJobThatDoesNotSayWhatItIsIsKeptAsAnUnreadableJob(): void
    {
        $envelope = $this->serializer()->decode(['body' => '{"anything":1}', 'headers' => ['Content-Type' => 'application/json']]);

        self::assertInstanceOf(UndecodableJob::class, $envelope->getMessage());
        self::assertSame('{"anything":1}', $envelope->getMessage()->originalBody);
        self::assertSame(0, $this->inner->decoded);
    }

    /**
     * The core has many classes; a queue may only build the ones a handler takes, and
     * none of the others, whatever their constructor does.
     */
    public function testAClassOfTheCoreNoHandlerTakesIsNeverBuilt(): void
    {
        $this->serializer([SendEmailMessage::class])->decode($this->encoded(ProbeMessage::class));

        $this->assertReadAsUndecodable(ProbeMessage::class);
    }

    public function testAClassNoHandlerTakesCannotBeQueued(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no handler takes it');

        $this->serializer([SendEmailMessage::class])->encode(new Envelope(new ProbeMessage('nobody')));
    }

    public function testAClassHandledThroughItsInterfaceIsLetThrough(): void
    {
        $this->serializer([DataTransferJobMessage::class])->decode($this->encoded(RunExportJob::class));

        self::assertSame(1, $this->inner->decoded);
    }

    public function testTheContainerListsTheClassesItsHandlersTake(): void
    {
        $handled = static::getContainer()->getParameter(HandledMessageClassesPass::PARAMETER);
        \assert(\is_array($handled));

        self::assertContains(SendEmailMessage::class, $handled);
        self::assertContains(RunExportJob::class, $handled);
        self::assertContains(UndecodableJob::class, $handled);
        self::assertNotContains(ProbeMessage::class, $handled);
    }

    private function assertReadAsUndecodable(string $originalType): void
    {
        self::assertSame(1, $this->inner->decoded, 'Only the stand-in is built.');
        self::assertSame(UndecodableJob::class, $this->inner->lastDecoded['headers']['type'] ?? null);
        $body = json_decode((string) ($this->inner->lastDecoded['body'] ?? ''), true);
        self::assertSame($originalType, $body['originalType'] ?? null);
    }

    /**
     * @param list<string> $handledMessageClasses by default, every class is taken by a
     *                                            handler: the namespaces alone decide
     */
    private function serializer(array $handledMessageClasses = ['*']): AllowedClassesSerializer
    {
        return new AllowedClassesSerializer($this->inner, [], $handledMessageClasses);
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
