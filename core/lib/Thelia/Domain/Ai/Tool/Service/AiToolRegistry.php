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

namespace Thelia\Domain\Ai\Tool\Service;

use Psr\Log\LoggerInterface;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Guardrail\AiSchemaValidator;
use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Tool\AiChangeProposal;
use Thelia\Domain\Ai\Tool\AiReadToolInterface;
use Thelia\Domain\Ai\Tool\AiToolApprovalStatus;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolChannel;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiToolDescriptor;
use Thelia\Domain\Ai\Tool\AiToolOffer;
use Thelia\Domain\Ai\Tool\AiToolRegistryInterface;
use Thelia\Domain\Ai\Tool\AiToolResult;
use Thelia\Domain\Ai\Tool\AiToolRisk;
use Thelia\Domain\Ai\Tool\AiWriteToolInterface;
use Thelia\Domain\Ai\Tool\Exception\AiToolFailedException;
use Thelia\Domain\Ai\Tool\Internal\AiToolDeclaration;
use Thelia\Domain\Ai\Tool\Internal\AiToolSchema;
use Thelia\Domain\Ai\Tool\Log\AiToolCallLogEntry;
use Thelia\Domain\Ai\Tool\Log\AiToolCallLogWriter;
use Thelia\Model\AdminQuery;
use Thelia\Model\AiToolApproval;

/**
 * The tools declared with #[AsAiTool], offered under the rights of the actor and run with their limits.
 *
 * Everything a call needs is decided here, each time: whether the tool is still offered to this actor, the
 * limits, the arguments against the schema. A refusal is an answer the model can read, never an exception.
 *
 * @internal
 */
final readonly class AiToolRegistry implements AiToolRegistryInterface
{
    public const int CALLS_PER_ANSWER = 8;

    private const string FAILED_MESSAGE = 'The tool failed.';

    public function __construct(
        private AiToolDeclarations $declarations,
        private AiToolInstances $instances,
        private AiUsageRegistryInterface $usages,
        private AiToolAccessChecker $accessChecker,
        private AiSchemaValidator $validator,
        private AiToolRateLimiter $limiter,
        private AiToolExecutionGuard $guard,
        private AiToolCallLogWriter $log,
        private AiActorHasher $hasher,
        private AiSettings $settings,
        private LoggerInterface $logger,
    ) {
    }

    public function offer(AiToolContext $context, ?array $only = null): AiToolOffer
    {
        if (AiToolChannel::BackOffice === $context->channel) {
            return new AiToolOffer([], $context->withOfferedTools([]), false);
        }

        $audience = $this->audienceOf($context);
        $definitions = [];
        $names = [];
        $risks = [];

        foreach ($this->declarations->all() as $name => $declaration) {
            if ((null !== $only && !\in_array($name, $only, true)) || !$this->isOffered($declaration, $context, $audience)) {
                continue;
            }

            $tool = $this->instances->get($name);
            $definitions[] = new AiToolDefinition($name, $declaration->attribute->description, AiToolSchema::forTransport($name, $tool->inputSchema()));
            $names[] = $name;

            foreach ($declaration->attribute->risks as $risk) {
                $risks[$risk->value] = true;
            }
        }

        return new AiToolOffer($definitions, $context->withOfferedTools($names), \count(AiToolRisk::cases()) === \count($risks));
    }

    public function call(AiToolCall $call, AiToolContext $context): AiToolResult
    {
        return $this->guard->run(fn (): AiToolResult => $this->run($call, $context));
    }

    public function answer(array $calls, AiToolContext $context): array
    {
        AiAssert::listOf($calls, AiToolCall::class, 'The tool calls of a response');

        $messages = [];

        $refused = null;

        foreach ($calls as $position => $call) {
            if ($position < self::CALLS_PER_ANSWER) {
                $result = $this->call($call, $context);
            } else {
                // One line of the journal for the whole surplus: a response that announces thousands of calls costs one write.
                $refused ??= $this->refuse($call, $context, $this->declarations->find($call->name), AiToolCallStatus::Limited, 'answer_limit: '.(\count($calls) - self::CALLS_PER_ANSWER).' calls', hrtime(true));
                $result = new AiToolResult($refused->status, $refused->content, null, $refused->logId);
            }

            $messages[] = $result->toMessage($call->id);
        }

        return $messages;
    }

    public function describe(): array
    {
        return array_values(array_map(static fn (AiToolDeclaration $declaration): AiToolDescriptor => $declaration->describe(), $this->declarations->all()));
    }

    private function run(AiToolCall $call, AiToolContext $context): AiToolResult
    {
        $started = hrtime(true);
        $declaration = $this->declarations->find($call->name);

        if (!$declaration instanceof AiToolDeclaration) {
            return $this->refuse($call, $context, null, AiToolCallStatus::UnknownTool, 'unknown_tool', $started);
        }

        if (!$context->isOffered($call->name) || !$this->stillOffered($declaration, $context)) {
            return $this->refuse($call, $context, $declaration, AiToolCallStatus::Denied, 'not_offered', $started);
        }

        if (AiToolChannel::Mcp !== $context->channel && !$this->limiter->consume($context)) {
            return $this->refuse($call, $context, $declaration, AiToolCallStatus::Limited, 'task_limit', $started);
        }

        $tool = $this->instances->get($call->name);
        $details = $this->validator->validate($tool->inputSchema(), $call->arguments);

        if ([] !== $details) {
            return $this->finish($call, $context, $declaration, AiToolCallStatus::InvalidArguments, ['error' => 'invalid_arguments', 'details' => $details], 'invalid_arguments: '.implode('; ', $details), null, $started);
        }

        try {
            return $tool instanceof AiWriteToolInterface
                ? $this->propose($tool, $call, $context, $declaration, $started)
                : $this->read($tool, $call, $context, $declaration, $started);
        } catch (AiToolFailedException $failure) {
            return $this->finish($call, $context, $declaration, AiToolCallStatus::Failed, ['error' => 'failed', 'message' => $failure->getMessage()], 'tool_failed', null, $started);
        } catch (\Throwable $exception) {
            $this->logger->error('The AI tool "{tool}" failed.', ['tool' => $call->name, 'exception' => $exception]);

            return $this->finish($call, $context, $declaration, AiToolCallStatus::Failed, ['error' => 'failed', 'message' => self::FAILED_MESSAGE], 'exception: '.$exception::class, null, $started);
        }
    }

    private function read(AiReadToolInterface $tool, AiToolCall $call, AiToolContext $context, AiToolDeclaration $declaration, int $started): AiToolResult
    {
        $result = $tool->execute($call->arguments, $context);

        if ([] !== $result && array_is_list($result)) {
            return $this->finish($call, $context, $declaration, AiToolCallStatus::Failed, ['error' => 'failed', 'message' => self::FAILED_MESSAGE], 'result_not_object', null, $started);
        }

        try {
            $encoded = json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return $this->finish($call, $context, $declaration, AiToolCallStatus::Failed, ['error' => 'failed', 'message' => self::FAILED_MESSAGE], 'result_not_encodable', null, $started);
        }

        if (\strlen($encoded) > $declaration->attribute->maxResultBytes) {
            return $this->finish($call, $context, $declaration, AiToolCallStatus::ResultTooLarge, ['error' => 'result_too_large', 'message' => 'Narrow the query or ask for fewer items.'], 'result_too_large', null, $started, \strlen($encoded));
        }

        return $this->finish($call, $context, $declaration, AiToolCallStatus::Succeeded, $result, null, null, $started, \strlen($encoded));
    }

    private function propose(AiWriteToolInterface $tool, AiToolCall $call, AiToolContext $context, AiToolDeclaration $declaration, int $started): AiToolResult
    {
        $proposal = $tool->propose($call->arguments, $context);
        $approval = $this->store($proposal, $call, $context, $declaration);
        $expiresAt = $approval->getExpiresAt();

        return $this->finish(
            $call,
            $context,
            $declaration,
            AiToolCallStatus::PendingApproval,
            ['status' => 'pending_approval', 'approval_id' => $approval->getId(), 'summary' => $proposal->summary, 'expires_at' => $expiresAt instanceof \DateTimeInterface ? $expiresAt->format(\DATE_ATOM) : null],
            null,
            $approval->getId(),
            $started,
        );
    }

    private function store(AiChangeProposal $proposal, AiToolCall $call, AiToolContext $context, AiToolDeclaration $declaration): AiToolApproval
    {
        $attribute = $declaration->attribute;
        $admin = AiActorType::Admin === $context->actor->type ? $context->actor : null;
        $adminRow = $admin instanceof AiActor ? AdminQuery::create()->findPk((int) $admin->identifier) : null;
        $token = null === $context->tokenId ? null : $this->accessChecker->tokenRow($context->tokenId);

        $approval = (new AiToolApproval())
            ->setToolName($attribute->name)
            ->setModule(strtolower($declaration->module))
            ->setAclResource($attribute->resource)
            ->setAclModule($attribute->module)
            ->setAclAccess($attribute->access)
            ->setTarget($proposal->target)
            ->setSummary($proposal->summary)
            ->setOriginalArguments(AiToolApprovalRepository::encode($call->arguments))
            ->setArguments(AiToolApprovalRepository::encode($call->arguments))
            ->setBeforeState(AiToolApprovalRepository::encode($proposal->before))
            ->setAfterState(AiToolApprovalRepository::encode($proposal->after))
            ->setAmended(false)
            ->setStatus(AiToolApprovalStatus::Pending->value)
            ->setChannel($context->channel->value)
            ->setUsageId(AiToolChannel::Mcp === $context->channel ? null : $context->usageId)
            ->setCorrelationId($context->correlationId)
            ->setProposedByType($context->actor->type->value)
            ->setProposedByHash($this->hasher->hash($context->actor))
            ->setProposedByAdminId($adminRow?->getId())
            ->setProposedByAdminLogin($adminRow?->getLogin())
            ->setTokenId($token?->getId())
            ->setTokenPrefix($token?->getTokenPrefix())
            // At a whole second: MySQL rounds a fraction, and the answer must name the expiry it stored.
            ->setExpiresAt(new \DateTimeImmutable(date('Y-m-d H:i:s', time() + 3600 * $this->settings->toolApprovalTtlHours())));
        $approval->save();

        return $approval;
    }

    /**
     * Records a refusal and answers like an unknown tool, for a tool that is not offered, and with the limit
     * message for a call over a limit.
     */
    private function refuse(AiToolCall $call, AiToolContext $context, ?AiToolDeclaration $declaration, AiToolCallStatus $status, string $reason, int $started): AiToolResult
    {
        if (AiToolCallStatus::Limited === $status) {
            $content = ['error' => 'limited', 'message' => 'Too many tool calls for this task, try again later.'];
        } else {
            $content = ['error' => 'unknown_tool', 'message' => 'Unknown tool: '.$call->name];
        }

        $result = $this->finish($call, $context, $declaration, $status, $content, $reason, null, $started, null, false);

        return AiToolCallStatus::Denied === $status
            ? new AiToolResult(AiToolCallStatus::UnknownTool, $result->content, null, $result->logId)
            : $result;
    }

    /**
     * @param array<string, mixed> $content
     */
    private function finish(AiToolCall $call, AiToolContext $context, ?AiToolDeclaration $declaration, AiToolCallStatus $status, array $content, ?string $error, ?int $approvalId, int $started, ?int $resultBytes = null, bool $measured = true): AiToolResult
    {
        $encoded = $measured ? json_encode([] === $content ? new \stdClass() : $content, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : false;
        $bytes = $resultBytes ?? (\is_string($encoded) && \in_array($status, [AiToolCallStatus::Succeeded, AiToolCallStatus::PendingApproval], true) ? \strlen($encoded) : null);

        $logId = $this->log->write(new AiToolCallLogEntry(
            $call->name,
            null === $declaration ? '' : strtolower($declaration->module),
            $declaration?->effect->value ?? 'unknown',
            $context->channel,
            $status,
            $error,
            AiToolChannel::Mcp === $context->channel ? null : $context->usageId,
            $context->correlationId,
            $context->actor,
            $context->tokenId,
            $approvalId,
            $call->arguments,
            $bytes,
            intdiv(hrtime(true) - $started, 1_000_000),
        ));

        return new AiToolResult($status, $content, $approvalId, $logId);
    }

    private function audienceOf(AiToolContext $context): AiAudience
    {
        return AiToolChannel::Mcp === $context->channel ? AiAudience::Admin : $this->usages->get($context->usageId)->audience;
    }

    private function stillOffered(AiToolDeclaration $declaration, AiToolContext $context): bool
    {
        try {
            return $this->isOffered($declaration, $context, $this->audienceOf($context));
        } catch (UnknownAiUsageException) {
            return false;
        }
    }

    /**
     * The rule of the offer: the audience of the usage is one of the tool, the channel and the actor fit it and,
     * for administrators, the right is held now (and covered by the token, through MCP).
     */
    private function isOffered(AiToolDeclaration $declaration, AiToolContext $context, AiAudience $audience): bool
    {
        $attribute = $declaration->attribute;

        if (AiAudience::System === $audience || !\in_array($audience, $attribute->audiences, true)) {
            return false;
        }

        if (AiToolChannel::BackOffice === $context->channel) {
            return false;
        }

        if (AiToolChannel::Mcp === $context->channel && (!$attribute->mcp || null === $context->tokenId || !$this->settings->isEnabled() || !$this->settings->mcpEnabled())) {
            return false;
        }

        return match ($audience) {
            AiAudience::Admin => $this->accessChecker->holdsRight($context->actor, $declaration, $context->tokenId),
            AiAudience::Customer => AiActorType::Customer === $context->actor->type,
            AiAudience::Public => true,
            AiAudience::System => false,
        };
    }
}
