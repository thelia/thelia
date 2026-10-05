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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Lets only the jobs of the shop in and out of a queue.
 *
 * A queued job is data that gets turned back into objects, and whoever can write to
 * the queue chooses their classes. The envelope is JSON, never PHP serialization, and
 * before anything is built from it the message class and every stamp class are
 * checked: the message is a class of the core, of an active module or one of the
 * few Symfony messages the shop sends (an e-mail); a stamp is a Messenger stamp or
 * one of those same namespaces. A stamp carrying serializer context is refused, as
 * it would let the queue reconfigure how the rest of the envelope is read.
 *
 * The same check runs when a job is queued, so a module that dispatches a class the
 * workers would refuse learns it on dispatch, not from a queue that never empties.
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

    /**
     * @param list<string> $extraAllowedClasses classes a project adds, by exact name
     */
    public function __construct(
        #[Autowire(service: 'messenger.transport.symfony_serializer')]
        private SerializerInterface $inner,
        #[Autowire(param: 'thelia.messenger.allowed_message_classes')]
        private array $extraAllowedClasses = [],
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $headers = $encodedEnvelope['headers'] ?? null;

        if (!\is_array($headers) || !\is_string($headers['type'] ?? null)) {
            throw new MessageDecodingFailedException('Encoded envelope does not have a "type" header.');
        }

        $this->assertAllowedMessage($headers['type'], MessageDecodingFailedException::class);

        foreach (array_keys($headers) as $name) {
            if (\is_string($name) && str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                $this->assertAllowedStamp(substr($name, \strlen(self::STAMP_HEADER_PREFIX)), MessageDecodingFailedException::class);
            }
        }

        return $this->inner->decode($encodedEnvelope);
    }

    public function encode(Envelope $envelope): array
    {
        $this->assertAllowedMessage($envelope->getMessage()::class, \LogicException::class);

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
        if (\in_array($class, self::ALLOWED_SYMFONY_MESSAGES, true)
            || \in_array($class, $this->extraAllowedClasses, true)
            || $this->isInShopNamespace($class)
        ) {
            return;
        }

        throw new $exceptionClass(\sprintf('The message class "%s" is not one the shop queues: only classes of the core, of an active module, or listed in thelia.messenger.allowed_message_classes are.', $class));
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
        return ModuleQuery::create()
            ->filterByCode(substr($class, 0, $separator))
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->exists();
    }
}
