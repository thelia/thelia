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

namespace Thelia\Domain\Ai;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * Who the call is made for: the key of the rate limit and, hashed, the actor of the log. The gateway
 * resolves it by itself (AiActorResolverInterface); set AiRequest::$actor only to speak for someone else,
 * like a job working for a customer.
 */
#[Exclude]
final readonly class AiActor
{
    private function __construct(
        public AiActorType $type,
        public string $identifier,
    ) {
        AiAssert::notBlank($identifier, 'The identifier of an actor');

        if (\strlen($identifier) > 255) {
            throw new Exception\InvalidAiArgumentException('The identifier of an actor holds at most 255 bytes.');
        }
    }

    public static function admin(int $adminId): self
    {
        return new self(AiActorType::Admin, (string) $adminId);
    }

    public static function customer(int $customerId): self
    {
        return new self(AiActorType::Customer, (string) $customerId);
    }

    /**
     * @param string $sessionIdOrIp the session identifier, or the IP address when there is no session
     */
    public static function visitor(string $sessionIdOrIp): self
    {
        return new self(AiActorType::Visitor, $sessionIdOrIp);
    }

    /**
     * An actor already reduced to its HMAC, as the log keeps it: what a guardrail that may call a third party
     * receives in place of the real actor. The gateway builds it; a module has no use for it.
     *
     * @internal
     *
     * @param string $hash the HMAC-SHA256 of `<type>:<identifier>` (AiActorHasher)
     */
    public static function pseudonymous(AiActorType $type, string $hash): self
    {
        return new self($type, $hash);
    }

    /**
     * @param string $process the command or the job, like `pricewatch:collect`
     */
    public static function system(string $process = 'cli'): self
    {
        return new self(AiActorType::System, $process);
    }
}
