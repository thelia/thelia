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
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * What a module asks the gateway. Everything but the usage and the messages is optional; what the request
 * uses (tools, a schema) must have been declared by its usage.
 *
 * The gateway does not run the tools: when the response holds tool calls, the module runs them and sends
 * a new request with withMessages(AiMessage::fromResponse($response), AiMessage::toolResult(...)), under
 * the same correlation identifier so that the log groups the turns.
 */
#[Exclude]
final readonly class AiRequest
{
    public const int OUTPUT_TOKENS_LIMIT = 1_000_000;

    /**
     * @param list<AiMessage>                     $messages          the conversation; the last one is a user message or a tool result
     * @param string|null                         $system            the instructions of the module, never data typed by someone
     * @param list<UntrustedContent>              $untrustedContents data to read, never to obey
     * @param list<AiToolDefinition>              $tools             tools the module runs itself
     * @param int|null                            $maxOutputTokens   below the limit of the usage; null takes that limit
     * @param array<string, array<string, mixed>> $providerOptions   native options by provider, applied only when the resolved
     *                                                               connection is of that provider: ['anthropic' => ['effort' => 'high']].
     *                                                               A way out that nothing governs: no declaration, and a tool the
     *                                                               provider runs (a web plugin) costs no more than the provider announces
     * @param string|null                         $correlationId     groups the turns of one task in the log
     * @param AiActor|null                        $actor             null: the gateway resolves who the call is made for
     */
    public function __construct(
        public string $usageId,
        public array $messages,
        public ?string $system = null,
        public array $untrustedContents = [],
        public ?AiOutputSchema $outputSchema = null,
        public array $tools = [],
        public ?int $maxOutputTokens = null,
        public array $providerOptions = [],
        public ?string $correlationId = null,
        public ?AiActor $actor = null,
    ) {
        AiAssert::usageId($usageId);
        self::assertConversation($messages);

        if (null !== $system) {
            AiAssert::notBlank($system, 'The system prompt');
        }

        AiAssert::listOf($untrustedContents, UntrustedContent::class, 'The untrusted contents');
        AiAssert::listOf($tools, AiToolDefinition::class, 'The tools');

        $toolNames = array_map(static fn (AiToolDefinition $tool): string => $tool->name, $tools);

        if (\count($toolNames) !== \count(array_unique($toolNames))) {
            throw new InvalidAiArgumentException('Two tools of a request have the same name.');
        }

        if (null !== $maxOutputTokens && ($maxOutputTokens < 1 || $maxOutputTokens > self::OUTPUT_TOKENS_LIMIT)) {
            throw new InvalidAiArgumentException(\sprintf('The output limit is between 1 and %d tokens, %d given.', self::OUTPUT_TOKENS_LIMIT, $maxOutputTokens));
        }

        AiAssert::providerOptions($providerOptions);

        if (null !== $correlationId) {
            AiAssert::token($correlationId, 'A correlation identifier');
        }
    }

    /**
     * The same request with these messages added at the end of the conversation: the next turn of a tool loop.
     */
    public function withMessages(AiMessage ...$messages): self
    {
        return new self(
            $this->usageId,
            [...$this->messages, ...array_values($messages)],
            $this->system,
            $this->untrustedContents,
            $this->outputSchema,
            $this->tools,
            $this->maxOutputTokens,
            $this->providerOptions,
            $this->correlationId,
            $this->actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsFor(AiProvider $provider): array
    {
        return $this->providerOptions[$provider->value] ?? [];
    }

    /**
     * @param array<mixed> $messages
     */
    private static function assertConversation(array $messages): void
    {
        AiAssert::listOf($messages, AiMessage::class, 'The messages');

        if ([] === $messages) {
            throw new InvalidAiArgumentException('A request holds at least one message.');
        }

        $calledTools = [];

        /** @var AiMessage $message */
        foreach ($messages as $message) {
            foreach ($message->toolCalls as $toolCall) {
                $calledTools[$toolCall->id] = true;
            }

            if (AiMessageRole::Tool === $message->role && !isset($calledTools[(string) $message->toolCallId])) {
                throw new InvalidAiArgumentException(\sprintf('The tool result "%s" answers no tool call of an earlier assistant message.', $message->toolCallId));
            }
        }

        if (AiMessageRole::Assistant === end($messages)->role) {
            throw new InvalidAiArgumentException('The last message of a request is a user message or a tool result: the model answers it.');
        }
    }
}
