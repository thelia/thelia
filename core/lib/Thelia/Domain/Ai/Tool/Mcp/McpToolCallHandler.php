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

namespace Thelia\Domain\Ai\Tool\Mcp;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolRegistryInterface;

/**
 * `tools/call`: runs the call through the registry with the context of the token, and renders the answer as the
 * contract says. A write tool comes back as a proposal that waits for a human decision: nothing here applies it.
 * A tool that is not offered gets the answer of a tool that does not exist.
 *
 * @implements RequestHandlerInterface<CallToolResult>
 *
 * @internal
 */
final readonly class McpToolCallHandler implements RequestHandlerInterface
{
    public function __construct(
        private AiToolRegistryInterface $registry,
        private McpToolContextFactory $contexts,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof CallToolRequest;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        \assert($request instanceof CallToolRequest);

        $context = $this->contexts->create();

        if (null === $context) {
            return Error::forInvalidRequest('Unauthenticated', $request->getId());
        }

        try {
            $call = new AiToolCall((string) $request->getId(), $request->name, $request->arguments);
        } catch (InvalidAiArgumentException) {
            return $this->unknown($request);
        }

        $offered = $this->registry->offer($context, [$request->name])->context;
        $result = $this->registry->call($call, $offered);

        if (\in_array($result->status, [AiToolCallStatus::UnknownTool, AiToolCallStatus::Denied], true)) {
            return $this->unknown($request);
        }

        $isError = !\in_array($result->status, [AiToolCallStatus::Succeeded, AiToolCallStatus::PendingApproval], true);
        $structured = [] === $result->content ? new \stdClass() : $result->content;

        return new Response($request->getId(), new CallToolResult(
            [new TextContent(json_encode($structured, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR))],
            $isError,
            $structured,
        ));
    }

    private function unknown(CallToolRequest $request): Error
    {
        return Error::forInvalidParams('Unknown tool: '.$request->name, $request->getId());
    }
}
