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
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Request\ListResourcesRequest;
use Mcp\Schema\Request\ListResourceTemplatesRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Request\PingRequest;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Every method the MCP server of the core does not serve is a method that does not exist: completion, resource
 * subscriptions, the log level, and whatever a later version of the SDK adds. The server announces tools only; the
 * handlers of the SDK for the rest would answer without the tools registry in between.
 *
 * The served methods are listed, not the refused ones: a method new to the SDK stays refused until someone serves it.
 *
 * @implements RequestHandlerInterface<mixed>
 *
 * @internal
 */
final readonly class McpUnservedMethodsHandler implements RequestHandlerInterface
{
    /** @var list<class-string<Request>> */
    private const array SERVED = [
        InitializeRequest::class,
        PingRequest::class,
        ListToolsRequest::class,
        CallToolRequest::class,
        ListPromptsRequest::class,
        GetPromptRequest::class,
        ListResourcesRequest::class,
        ListResourceTemplatesRequest::class,
        ReadResourceRequest::class,
    ];

    public function supports(Request $request): bool
    {
        foreach (self::SERVED as $served) {
            if ($request instanceof $served) {
                return false;
            }
        }

        return true;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        return Error::forMethodNotFound(\sprintf('Method not found: %s', $request::getMethod()), $request->getId());
    }
}
