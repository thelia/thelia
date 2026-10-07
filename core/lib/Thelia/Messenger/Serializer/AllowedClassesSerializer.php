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

namespace Thelia\Messenger\Serializer;

use Propel\Runtime\ActiveQuery\QueryExecutor\QueryExecutionException;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Messenger\Message\UndecodableJob;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Lets only the jobs of the shop in and out of a queue.
 *
 * A queued job is data that gets turned back into objects, and whoever can write to
 * the queue chooses their classes. The envelope is JSON, never PHP serialization, and
 * before anything is built from it the message class and every stamp class are
 * checked: the message is a class of the core, of an active module or one of the
 * few Symfony messages the shop sends (an e-mail), and one a handler of the
 * application takes, so no other class of those namespaces is ever built from a
 * queue; a stamp is a Messenger stamp or one of those same namespaces. A stamp carrying serializer context is refused, as
 * it would let the queue reconfigure how the rest of the envelope is read.
 *
 * A queued mail holds the content of its attachments, never the path of a file the
 * worker would read ({@see QueuedMailFiles}).
 *
 * The same check runs when a job is queued, so a module that dispatches a class the
 * workers would refuse learns it on dispatch, not from a queue that never empties.
 *
 * A queued job that fails the check, or can no longer be built, is read as an
 * UndecodableJob rather than refused: Symfony deletes a message it cannot decode, and
 * the job would vanish without a trace.
 */
final readonly class AllowedClassesSerializer implements SerializerInterface
{
    public const ALLOWED_SYMFONY_MESSAGES = [
        SendEmailMessage::class,
    ];

    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    private const ALLOWED_STAMP_NAMESPACES = [
        'Symfony\\Component\\Messenger\\Stamp\\',
        'Symfony\\Component\\Messenger\\Bridge\\',
    ];

    private const CORE_NAMESPACE = 'Thelia\\';

    private const MAIL_READING_A_FILE = 'A queued mail cannot name a file of the server to read: only the content of an attachment is queued.';

    /**
     * @param list<string> $extraAllowedClasses   classes a project adds, by exact name
     * @param list<string> $handledMessageClasses the message classes (or their parents
     *                                            and interfaces) a handler takes
     */
    public function __construct(
        #[Autowire(service: 'messenger.transport.symfony_serializer')]
        private SerializerInterface $inner,
        #[Autowire(param: 'thelia.messenger.allowed_message_classes')]
        private array $extraAllowedClasses,
        #[Autowire(param: 'thelia.messenger.handled_message_classes')]
        private array $handledMessageClasses,
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $headers = $encodedEnvelope['headers'] ?? null;

        if (!\is_array($headers) || !\is_string($headers['type'] ?? null)) {
            return new Envelope(new UndecodableJob('', 'The queued job does not say what class it is.', (string) ($encodedEnvelope['body'] ?? '')));
        }

        try {
            $this->assertAllowedMessage($headers['type'], MessageDecodingFailedException::class);

            foreach (array_keys($headers) as $name) {
                if (\is_string($name) && str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                    $this->assertAllowedStamp(substr($name, \strlen(self::STAMP_HEADER_PREFIX)), MessageDecodingFailedException::class);
                }
            }

            $envelope = $this->inner->decode($encodedEnvelope);
        } catch (MessageDecodingFailedException $exception) {
            return $this->undecodable($headers, (string) ($encodedEnvelope['body'] ?? ''), $exception->getMessage());
        }

        $message = $envelope->getMessage();

        if ($message instanceof SendEmailMessage && QueuedMailFiles::readsAFile($message)) {
            return $this->undecodable($headers, (string) ($encodedEnvelope['body'] ?? ''), self::MAIL_READING_A_FILE);
        }

        return $envelope;
    }

    /**
     * The job as an UndecodableJob, with the stamps it carried that the shop reads:
     * the failure transport keeps its date, its attempts and its reason.
     *
     * @param array<array-key, mixed> $headers
     */
    private function undecodable(array $headers, string $body, string $reason): Envelope
    {
        $message = new UndecodableJob((string) $headers['type'], $reason, $body);
        $readableHeaders = ['type' => UndecodableJob::class, 'Content-Type' => 'application/json'];

        foreach ($headers as $name => $value) {
            if (\is_string($name) && str_starts_with($name, self::STAMP_HEADER_PREFIX) && $this->isAllowedStamp(substr($name, \strlen(self::STAMP_HEADER_PREFIX)))) {
                $readableHeaders[$name] = $value;
            }
        }

        try {
            return $this->inner->decode([
                'body' => json_encode(['originalType' => $message->originalType, 'reason' => $reason, 'originalBody' => $body], \JSON_THROW_ON_ERROR),
                'headers' => $readableHeaders,
            ]);
        } catch (\Throwable) {
            // Even its stamps cannot be read: the job is kept, without them.
            return new Envelope($message);
        }
    }

    private function isAllowedStamp(string $class): bool
    {
        try {
            $this->assertAllowedStamp($class, \LogicException::class);

            return true;
        } catch (\LogicException) {
            return false;
        }
    }

    public function encode(Envelope $envelope): array
    {
        $this->assertAllowedMessage($envelope->getMessage()::class, \LogicException::class);

        $envelope = QueuedMailFiles::inline($envelope);
        $message = $envelope->getMessage();

        if ($message instanceof SendEmailMessage && QueuedMailFiles::readsAFile($message)) {
            throw new \LogicException(self::MAIL_READING_A_FILE);
        }

        foreach (array_keys($envelope->all()) as $stampClass) {
            $this->assertAllowedStamp($stampClass, \LogicException::class);
        }

        return $this->inner->encode($envelope);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    private function assertAllowedMessage(string $class, string $exceptionClass): void
    {
        if (\in_array($class, $this->extraAllowedClasses, true)) {
            return;
        }

        if (!\in_array($class, self::ALLOWED_SYMFONY_MESSAGES, true) && !$this->isInShopNamespace($class)) {
            throw new $exceptionClass(\sprintf('The message class "%s" is not one the shop queues: only classes of the core, of an active module, or listed in thelia.messenger.allowed_message_classes are.', $class));
        }

        if (!$this->isHandled($class)) {
            throw new $exceptionClass(\sprintf('The message class "%s" is not one the shop queues: no handler takes it.', $class));
        }
    }

    /**
     * Only called once the class is known to be of the shop: loading it to read its
     * parents runs no code a queue chose.
     */
    private function isHandled(string $class): bool
    {
        if (\in_array('*', $this->handledMessageClasses, true) || \in_array($class, $this->handledMessageClasses, true)) {
            return true;
        }

        if (!class_exists($class)) {
            return false;
        }

        return [] !== array_intersect([...class_parents($class), ...class_implements($class)], $this->handledMessageClasses);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    private function assertAllowedStamp(string $class, string $exceptionClass): void
    {
        if (SerializerStamp::class === $class) {
            throw new $exceptionClass('A queued message cannot carry serializer context.');
        }

        foreach (self::ALLOWED_STAMP_NAMESPACES as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return;
            }
        }

        if (\in_array($class, $this->extraAllowedClasses, true) || $this->isInShopNamespace($class)) {
            return;
        }

        throw new $exceptionClass(\sprintf('The stamp class "%s" is not one the shop queues.', $class));
    }

    private function isInShopNamespace(string $class): bool
    {
        if (str_starts_with($class, self::CORE_NAMESPACE)) {
            return true;
        }

        $separator = strpos($class, '\\');

        if (false === $separator || 0 === $separator) {
            return false;
        }

        // A module lives under the namespace named after its code. Read each time: a
        // worker outlives the activation and the deactivation of modules.
        $code = substr($class, 0, $separator);

        try {
            return self::isActiveModule($code);
        } catch (\PDOException|QueryExecutionException) {
            // A job is read before anything else runs for it: after a long wait, the
            // server may have closed the connection of the worker. Opened again once.
            Propel::getServiceContainer()->getConnectionManager(DatabaseConfiguration::THELIA_CONNECTION_NAME)->closeConnections();

            return self::isActiveModule($code);
        }
    }

    private static function isActiveModule(string $code): bool
    {
        return ModuleQuery::create()
            ->filterByCode($code)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->exists();
    }
}
