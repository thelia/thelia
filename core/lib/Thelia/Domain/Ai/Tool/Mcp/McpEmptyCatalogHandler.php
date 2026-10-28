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

use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Request\ListResourcesRequest;
use Mcp\Schema\Request\ListResourceTemplatesRequest;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListResourceTemplatesResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Prompts and resources: the MCP server of the core exposes tools only, so every list is empty and nothing can be
 * read. It stands before the handlers of the SDK, which would serve the prompts and resources of any bundle.
 *
 * @implements RequestHandlerInterface<mixed>
 *
 * @internal
 */
final readonly class McpEmptyCatalogHandler implements RequestHandlerInterface
{
    public function supports(Request $request): bool
    {
        return $request instanceof ListPromptsRequest
            || $request instanceof ListResourcesRequest
            || $request instanceof ListResourceTemplatesRequest
            || $request instanceof GetPromptRequest
            || $request instanceof ReadResourceRequest;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        return match (true) {
            $request instanceof ListPromptsRequest => new Response($request->getId(), new ListPromptsResult([], null)),
            $request instanceof ListResourcesRequest => new Response($request->getId(), new ListResourcesResult([], null)),
            $request instanceof ListResourceTemplatesRequest => new Response($request->getId(), new ListResourceTemplatesResult([], null)),
            $request instanceof GetPromptRequest => Error::forInvalidParams('Unknown prompt', $request->getId()),
            default => Error::forResourceNotFound('Unknown resource', $request->getId()),
        };
    }
}
