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

namespace Thelia\Domain\Ai\Tool;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * Who a tool call is made for and where it comes from.
 *
 * The public constructor builds a context of the gateway: an internal agent, no token, no list of tools offered.
 * The registry stamps the list of the tools it offers on the context it returns with the offer
 * (AiToolOffer::$context): a call for a tool outside that list, or on a context without one, is an unknown tool.
 * Only the MCP server of the core builds a context of the MCP channel, and only the approval service one of the
 * back office.
 */
#[Exclude]
final readonly class AiToolContext
{
    /** The usage identifier of the contexts that belong to no usage (MCP, back office): it is no real usage. */
    public const string MCP_USAGE_ID = 'thelia.mcp';

    public const string BACK_OFFICE_USAGE_ID = 'thelia.back_office';

    public AiToolChannel $channel;

    /** The MCP token the call authenticates with, null outside the MCP channel. */
    public ?int $tokenId;

    /** @var list<string>|null the tools the registry offered, null before offer() */
    public ?array $offeredTools;

    /**
     * @param AiActor     $actor         who the call is made for, in clear: a tool needs it. Take it from AiActorResolverInterface, never from the model
     * @param string      $usageId       the usage that runs the agent (AsAiUsage::$id); its audience decides which tools are offered
     * @param string|null $correlationId groups the calls of one task; the calls of one task share a limit
     */
    public function __construct(
        public AiActor $actor,
        public string $usageId,
        public ?string $correlationId = null,
        public string $locale = 'en_US',
    ) {
        AiAssert::notBlank($usageId, 'The usage of a tool context');
        AiAssert::notBlank($locale, 'The locale of a tool context');

        if (null !== $correlationId) {
            AiAssert::token($correlationId, 'The correlation identifier of a tool context');
        }

        $this->channel = AiToolChannel::Gateway;
        $this->tokenId = null;
        $this->offeredTools = null;
    }

    /**
     * The context of a call that came in through the MCP server of the core.
     *
     * @internal
     */
    public static function forMcp(AiActor $actor, int $tokenId, string $locale = 'en_US'): self
    {
        if ($tokenId < 1) {
            throw new InvalidAiArgumentException('The token of an MCP context is a stored token.');
        }

        return self::derive(new self($actor, self::MCP_USAGE_ID, null, $locale), AiToolChannel::Mcp, $tokenId, null);
    }

    /**
     * The context the approval service gives propose() when an administrator amends a proposal: the actor is the approver.
     *
     * @internal
     */
    public static function forBackOffice(AiActor $approver, ?string $usageId, ?string $correlationId, string $locale = 'en_US'): self
    {
        return self::derive(new self($approver, $usageId ?? self::BACK_OFFICE_USAGE_ID, $correlationId, $locale), AiToolChannel::BackOffice, null, null);
    }

    /**
     * @param list<string> $names
     *
     * @internal Called by the registry when it offers tools
     */
    public function withOfferedTools(array $names): self
    {
        return self::derive($this, $this->channel, $this->tokenId, array_values(array_unique($names)));
    }

    public function isOffered(string $toolName): bool
    {
        return null !== $this->offeredTools && \in_array($toolName, $this->offeredTools, true);
    }

    /**
     * @param list<string>|null $offeredTools
     */
    private static function derive(self $base, AiToolChannel $channel, ?int $tokenId, ?array $offeredTools): self
    {
        $copy = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $copy->actor = $base->actor;
        $copy->usageId = $base->usageId;
        $copy->correlationId = $base->correlationId;
        $copy->locale = $base->locale;
        $copy->channel = $channel;
        $copy->tokenId = $tokenId;
        $copy->offeredTools = $offeredTools;

        return $copy;
    }
}
